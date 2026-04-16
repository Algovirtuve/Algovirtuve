<?php

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view their recipe page', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $ownedRecipe = Recipe::factory()->for($user, 'owner')->createOne([
        'title' => 'Owned recipe',
    ]);

    Recipe::factory()->createOne([
        'title' => 'Other recipe',
    ]);

    $response = $this->actingAs($user)->get(route('recipes.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('recipes/index')
        ->where('recipes.0.title', $ownedRecipe->title)
        ->where('recipes.0.difficulty_label', $ownedRecipe->difficulty->label())
        ->where('recipes.0.status_label', $ownedRecipe->status->label())
        ->where('recipes.0.diet_type_label', $ownedRecipe->diet_type->label())
        ->where('recipes.0.meal_label', $ownedRecipe->meal->label())
        ->where('difficulties.0.label', RecipeDifficulty::cases()[0]->label())
        ->where('meals.0.label', Meal::cases()[0]->label())
        ->missing('recipes.1')
    );
});

test('authenticated users can update their own recipe', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne([
        'status' => RecipeStatus::Draft,
    ]);

    $response = $this->actingAs($user)->patch(route('recipes.update', $recipe), [
        'title' => 'Updated recipe',
        'instructions' => 'Updated instructions',
        'preparation_time' => '45 minutes',
        'servings' => 4,
        'difficulty' => RecipeDifficulty::Hard->value,
        'calorie_intake' => 640,
        'diet_type' => DietType::Vegetarian->value,
        'meal' => Meal::Dinner->value,
    ]);

    $response->assertRedirect(route('recipes.index', absolute: false));

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'title' => 'Updated recipe',
        'instructions' => 'Updated instructions',
        'preparation_time' => '45 minutes',
        'servings' => 4,
        'difficulty' => RecipeDifficulty::Hard->value,
        'calorie_intake' => 640,
        'status' => RecipeStatus::Draft->value,
        'diet_type' => DietType::Vegetarian->value,
        'meal' => Meal::Dinner->value,
    ]);
});

test('authenticated users cannot change recipe status through the user update route', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne([
        'status' => RecipeStatus::Draft,
    ]);

    $response = $this->actingAs($user)->patch(route('recipes.update', $recipe), [
        'title' => 'Status locked recipe',
        'instructions' => 'Same recipe with a blocked status change.',
        'preparation_time' => '30 minutes',
        'servings' => 3,
        'difficulty' => RecipeDifficulty::Medium->value,
        'calorie_intake' => 500,
        'status' => RecipeStatus::Accepted->value,
        'diet_type' => DietType::Keto->value,
        'meal' => Meal::Lunch->value,
    ]);

    $response->assertRedirect(route('recipes.index', absolute: false));

    expect($recipe->fresh()->status)->toBe(RecipeStatus::Draft);
});

test('diet type must be one of the allowed enum values', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne();

    $response = $this->actingAs($user)->from(route('recipes.index'))->patch(route('recipes.update', $recipe), [
        'title' => 'Invalid diet type recipe',
        'instructions' => 'This should fail validation.',
        'preparation_time' => '25 minutes',
        'servings' => 2,
        'difficulty' => RecipeDifficulty::Easy->value,
        'calorie_intake' => 280,
        'diet_type' => 'balanced',
        'meal' => Meal::Breakfast->value,
    ]);

    $response->assertRedirect(route('recipes.index', absolute: false));
    $response->assertSessionHasErrors('diet_type');
});

test('meal must be one of the allowed enum values', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne();

    $response = $this->actingAs($user)->from(route('recipes.index'))->patch(route('recipes.update', $recipe), [
        'title' => 'Invalid meal recipe',
        'instructions' => 'This should fail validation.',
        'preparation_time' => '25 minutes',
        'servings' => 2,
        'difficulty' => RecipeDifficulty::Easy->value,
        'calorie_intake' => 280,
        'diet_type' => DietType::Owned->value,
        'meal' => 'brunch',
    ]);

    $response->assertRedirect(route('recipes.index', absolute: false));
    $response->assertSessionHasErrors('meal');
});

test('users cannot update another users recipe', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne();

    $response = $this->actingAs($user)->patch(route('recipes.update', $recipe), [
        'title' => 'Blocked update',
        'instructions' => 'Blocked update',
        'preparation_time' => '10 minutes',
        'servings' => 2,
        'difficulty' => RecipeDifficulty::Easy->value,
        'calorie_intake' => 320,
        'diet_type' => DietType::Owned->value,
        'meal' => Meal::Lunch->value,
    ]);

    $response->assertNotFound();
    $this->assertDatabaseMissing('recipes', [
        'id' => $recipe->id,
        'title' => 'Blocked update',
    ]);
});

test('authenticated users can delete their own recipe', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->for($user, 'owner')->createOne();

    $response = $this->actingAs($user)->delete(route('recipes.destroy', $recipe));

    $response->assertRedirect(route('recipes.index', absolute: false));
    $this->assertDatabaseMissing('recipes', [
        'id' => $recipe->id,
    ]);
});

test('users cannot delete another users recipe', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne();

    $response = $this->actingAs($user)->delete(route('recipes.destroy', $recipe));

    $response->assertNotFound();
    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
    ]);
});
