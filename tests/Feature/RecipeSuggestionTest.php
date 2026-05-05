<?php

use App\Enums\DietType;
use App\Enums\IngredientCategory;
use App\Enums\Meal;
use App\Enums\Measurement;
use App\Enums\PreferenceStatus;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Enums\ToolType;
use App\Models\Ingredient;
use App\Models\Preference;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tool;
use App\Models\User;
use Database\Seeders\RecipeSeeder;
use Illuminate\Contracts\Concurrency\Driver;
use Illuminate\Support\Defer\DeferredCallback;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('suggestion generation uses concurrency for startup database queries', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Concurrent recipe',
        'status' => RecipeStatus::Accepted,
    ]);

    $syncDriver = new class implements Driver
    {
        public int $runs = 0;

        public function run(Closure|array $tasks): array
        {
            $this->runs++;

            if ($tasks instanceof Closure) {
                return [$tasks()];
            }

            return array_map(static fn ($task): mixed => $task(), $tasks);
        }

        public function defer(Closure|array $tasks): DeferredCallback
        {
            return new DeferredCallback(static function () use ($tasks): void {
                if ($tasks instanceof Closure) {
                    $tasks();

                    return;
                }

                foreach ($tasks as $task) {
                    $task();
                }
            });
        }
    };

    Concurrency::shouldReceive('run')
        ->once()
        ->withArgs(static fn (array $tasks): bool => count($tasks) === 6)
        ->andReturnUsing(function (array $tasks) use ($syncDriver): array {
            return $syncDriver->run($tasks);
        });

    $this->actingAs($user)
        ->get(route('suggestions.index'))
        ->assertOk();

    expect($syncDriver->runs)->toBe(1);
});

test('authenticated users can see their top ranked recipe suggestion', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $availableIngredient = Ingredient::query()->create([
        'category' => IngredientCategory::VEGETABLE,
    ]);
    $missingIngredient = Ingredient::query()->create([
        'category' => IngredientCategory::VEGETABLE,
    ]);
    $matchingTool = Tool::query()->create([
        'type' => ToolType::COOKING,
    ]);

    Product::query()->create([
        'title' => 'Tomato',
        'quantity' => 1,
        'measurement' => Measurement::UNIT,
        'ingredient_id' => $availableIngredient->id,
    ]);
    Product::query()->create([
        'title' => 'Onion',
        'quantity' => 1,
        'measurement' => Measurement::UNIT,
        'ingredient_id' => $missingIngredient->id,
    ]);
    Product::query()->create([
        'title' => 'Pan',
        'quantity' => 1,
        'measurement' => Measurement::UNIT,
        'tool_id' => $matchingTool->id,
    ]);

    $user->ingredients()->attach($availableIngredient->id, [
        'quantity' => 2,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);
    $user->tools()->attach($matchingTool->id);

    $higherRankedRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Tomato Skillet',
        'status' => RecipeStatus::Accepted,
        'diet_type' => DietType::Vegetarian,
        'meal' => Meal::Dinner,
        'difficulty' => RecipeDifficulty::Easy,
    ]);
    $lowerRankedRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Onion Soup',
        'status' => RecipeStatus::Accepted,
        'diet_type' => DietType::Keto,
        'meal' => Meal::Dinner,
        'difficulty' => RecipeDifficulty::Easy,
    ]);

    $higherRankedRecipe->ingredients()->attach($availableIngredient->id, [
        'quantity' => 2,
        'measurement' => Measurement::UNIT->value,
        'importance' => true,
    ]);
    $higherRankedRecipe->tools()->attach($matchingTool->id);

    $lowerRankedRecipe->ingredients()->attach($missingIngredient->id, [
        'quantity' => 2,
        'measurement' => Measurement::UNIT->value,
        'importance' => true,
    ]);

    $response = $this->actingAs($user)->get(route('suggestions.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Personalization/recipe_suggestion_page')
        ->where('suggestion.recipe.title', 'Tomato Skillet')
        ->where('suggestion.missing_ingredients_count', 0)
        ->where('remaining_suggestions_count', 2)
    );

    $this->assertDatabaseHas('preferences', [
        'user_id' => $user->id,
        'recipe_id' => $higherRankedRecipe->id,
        'preference_status' => PreferenceStatus::Awaiting->value,
    ]);

    $this->assertDatabaseMissing('preferences', [
        'user_id' => $user->id,
        'recipe_id' => $lowerRankedRecipe->id,
    ]);
});

test('reloading suggestions reuses the awaiting preference for the current head', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $recipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Stable suggestion',
        'status' => RecipeStatus::Accepted,
    ]);

    $this->actingAs($user)
        ->get(route('suggestions.index'))
        ->assertOk();

    $firstPreference = Preference::query()
        ->where('user_id', $user->id)
        ->where('recipe_id', $recipe->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(route('suggestions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestion.id', $firstPreference->id)
            ->where('suggestion.recipe.title', 'Stable suggestion')
        );

    expect(
        Preference::query()
            ->where('user_id', $user->id)
            ->where('preference_status', PreferenceStatus::Awaiting->value)
            ->count()
    )->toBe(1);
});

test('liking a suggestion updates the preference and advances the queue', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $firstRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'First ranked recipe',
        'status' => RecipeStatus::Accepted,
    ]);
    $secondRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Second ranked recipe',
        'status' => RecipeStatus::Accepted,
    ]);

    $response = $this->actingAs($user)->get(route('suggestions.index'));

    $response->assertOk();

    $firstPreference = Preference::query()
        ->where('user_id', $user->id)
        ->where('recipe_id', $firstRecipe->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('suggestions.like', $firstPreference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestion.recipe.title', 'Second ranked recipe')
            ->where('remaining_suggestions_count', 1)
        );

    $this->assertDatabaseHas('preferences', [
        'id' => $firstPreference->id,
        'preference_status' => PreferenceStatus::Liked->value,
    ]);

    expect(
        Preference::query()
            ->where('user_id', $user->id)
            ->where('recipe_id', $secondRecipe->id)
            ->firstOrFail()
            ->preference_status
    )->toBe(PreferenceStatus::Awaiting);

    expect(
        Preference::query()
            ->where('user_id', $user->id)
            ->where('preference_status', PreferenceStatus::Awaiting->value)
            ->count()
    )->toBe(1);
});

test('suggestion decision toast does not leak into the next request', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $firstRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'First ranked recipe',
        'status' => RecipeStatus::Accepted,
    ]);
    Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Second ranked recipe',
        'status' => RecipeStatus::Accepted,
    ]);

    $this->actingAs($user)->get(route('suggestions.index'))->assertOk();

    $firstPreference = Preference::query()
        ->where('user_id', $user->id)
        ->where('recipe_id', $firstRecipe->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('suggestions.like', $firstPreference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Recipe added to liked preferences.')
        );

    $this->actingAs($user)
        ->get(route('suggestions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.toast', null)
        );
});

test('get requests to suggestion decision routes redirect back to suggestions', function (string $routeName) {
    /** @var User $user */
    $user = User::factory()->createOne();
    $preference = Preference::factory()->for($user)->createOne([
        'preference_status' => PreferenceStatus::Awaiting,
    ]);

    $this->actingAs($user)
        ->get(route($routeName, $preference))
        ->assertRedirect(route('suggestions.index', absolute: false));
})->with([
    'like' => 'suggestions.like',
    'dislike' => 'suggestions.dislike',
]);

test('disliking the final suggestion returns an empty deck', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Only recipe',
        'status' => RecipeStatus::Accepted,
    ]);

    $this->actingAs($user)->get(route('suggestions.index'))->assertOk();

    $preference = Preference::query()
        ->where('user_id', $user->id)
        ->where('recipe_id', $recipe->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('suggestions.dislike', $preference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestion', null)
            ->where('remaining_suggestions_count', 0)
        );

    $this->assertDatabaseHas('preferences', [
        'id' => $preference->id,
        'preference_status' => PreferenceStatus::Disliked->value,
    ]);
});

test('suggestion generation is capped at fifty recipes', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    Recipe::factory()
        ->count(60)
        ->for($user, 'owner')
        ->create([
            'status' => RecipeStatus::Accepted,
        ]);

    $this->actingAs($user)
        ->get(route('suggestions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('remaining_suggestions_count', 50)
        );

    expect(
        Preference::query()
            ->where('user_id', $user->id)
            ->where('preference_status', PreferenceStatus::Awaiting->value)
            ->count()
    )->toBe(1);
});

test('recipe seeder stores real recipes with downloaded images and ingredients', function () {
    Storage::fake('public');

    Http::fake([
        'https://www.themealdb.com/api/json/v1/1/list.php?c=list' => Http::response([
            'meals' => [[
                'strCategory' => 'Side',
            ]],
        ]),
        'https://www.themealdb.com/api/json/v1/1/filter.php*' => Http::response([
            'meals' => [[
                'idMeal' => '52977',
                'strMeal' => 'Corba',
                'strMealThumb' => 'https://www.themealdb.com/images/media/meals/58oia61564916529.jpg',
            ], [
                'idMeal' => 'bad-meal',
                'strMeal' => 'Test',
                'strMealThumb' => '',
            ]],
        ]),
        'https://www.themealdb.com/api/json/v1/1/lookup.php?i=52977' => Http::response([
            'meals' => [[
                'idMeal' => '52977',
                'strMeal' => 'Corba',
                'strCategory' => 'Side',
                'strMealThumb' => 'https://www.themealdb.com/images/media/meals/58oia61564916529.jpg',
                'strInstructions' => 'Blend the vegetables, cook them in a pot, and serve warm.',
                'strIngredient1' => 'Lentils',
                'strMeasure1' => '1 cup',
                'strIngredient2' => 'Onion',
                'strMeasure2' => '1',
                'strIngredient3' => 'Carrot',
                'strMeasure3' => '2',
            ]],
        ]),
        'https://www.themealdb.com/api/json/v1/1/lookup.php?i=bad-meal' => Http::response([
            'meals' => [[
                'idMeal' => 'bad-meal',
                'strMeal' => 'Test',
                'strCategory' => 'Side',
                'strMealThumb' => '',
                'strInstructions' => 'Short text',
                'strIngredient1' => 'Salt',
                'strMeasure1' => '1 pinch',
                'strIngredient2' => '',
                'strMeasure2' => '',
                'strIngredient3' => '',
                'strMeasure3' => '',
            ]],
        ]),
        'https://www.themealdb.com/images/media/meals/*' => Http::response('image-bytes', 200),
    ]);

    $this->seed(RecipeSeeder::class);

    $recipe = Recipe::query()->where('title', 'Corba')->firstOrFail();

    expect($recipe->image_path)->not->toBeNull();
    expect($recipe->ingredients()->count())->toBeGreaterThan(0);
    expect($recipe->tools()->count())->toBeGreaterThan(0);
    expect(Storage::disk('public')->exists($recipe->image_path))->toBeTrue();
    expect(Recipe::query()->where('title', 'Test')->exists())->toBeFalse();
});
