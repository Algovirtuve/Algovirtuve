<?php

namespace Database\Seeders;

use App\Enums\DietType;
use App\Enums\IngredientCategory;
use App\Enums\Meal;
use App\Enums\Measurement;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Enums\ToolType;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RecipeSeeder extends Seeder
{
    private const TARGET_RECIPE_COUNT = 200;

    private const DETAIL_BATCH_SIZE = 25;

    public function run(): void
    {
        $owner = User::query()->first() ?? User::factory()->createOne();
        $meals = collect($this->mealSummaries())
            ->chunk(self::DETAIL_BATCH_SIZE)
            ->flatMap(fn ($chunk): array => $this->fetchMealDetails($chunk->all()))
            ->filter(fn (array $meal): bool => $this->isSeedableMeal($meal))
            ->unique('idMeal')
            ->take(self::TARGET_RECIPE_COUNT)
            ->values();

        $meals
            ->each(fn (array $meal): Recipe => $this->seedMeal($meal, $owner));
    }

    /**
     * @return list<array{idMeal: string, strMeal: string, strMealThumb: string}>
     */
    private function mealSummaries(): array
    {
        return collect($this->categories())
            ->flatMap(fn (string $category): array => $this->fetchCategoryMeals($category))
            ->filter(fn (array $meal): bool => trim((string) ($meal['strMealThumb'] ?? '')) !== '')
            ->unique('idMeal')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $categories = $this->fetchListValues('c', 'strCategory');

        if ($categories !== []) {
            return $categories;
        }

        return [
            'Beef',
            'Breakfast',
            'Chicken',
            'Dessert',
            'Goat',
            'Lamb',
            'Miscellaneous',
            'Pasta',
            'Pork',
            'Seafood',
            'Side',
            'Starter',
            'Vegan',
            'Vegetarian',
        ];
    }

    /**
     * @return list<string>
     */
    private function fetchListValues(string $parameter, string $field): array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get('https://www.themealdb.com/api/json/v1/1/list.php', [
                    $parameter => 'list',
                ]);

            if (! $response->successful()) {
                return [];
            }

            /** @var list<array<string, mixed>>|null $meals */
            $meals = $response->json('meals');

            return collect($meals ?? [])
                ->map(fn (array $meal): string => trim((string) ($meal[$field] ?? '')))
                ->filter(fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{idMeal: string, strMeal: string, strMealThumb: string}>
     */
    private function fetchCategoryMeals(string $category): array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get('https://www.themealdb.com/api/json/v1/1/filter.php', [
                    'c' => $category,
                ]);

            if (! $response->successful()) {
                return [];
            }

            /** @var list<array<string, mixed>>|null $meals */
            $meals = $response->json('meals');

            return $meals ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array{idMeal: string, strMeal: string, strMealThumb: string}>  $mealSummaries
     * @return list<array<string, mixed>>
     */
    private function fetchMealDetails(array $mealSummaries): array
    {
        if ($mealSummaries === []) {
            return [];
        }

        try {
            $responses = Http::pool(fn ($pool) => collect($mealSummaries)
                ->map(fn (array $mealSummary) => $pool
                    ->as((string) $mealSummary['idMeal'])
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->retry(2, 200)
                    ->get('https://www.themealdb.com/api/json/v1/1/lookup.php', [
                        'i' => $mealSummary['idMeal'],
                    ]))
                ->all());

            return collect($mealSummaries)
                ->map(function (array $mealSummary) use ($responses): ?array {
                    $response = $responses[(string) $mealSummary['idMeal']] ?? null;

                    if ($response === null || ! $response->successful()) {
                        return null;
                    }

                    /** @var list<array<string, mixed>>|null $meals */
                    $meals = $response->json('meals');

                    return $meals[0] ?? null;
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $meal
     */
    private function seedMeal(array $meal, User $owner): Recipe
    {
        $ingredientPayload = $this->ingredientPayload($meal);
        $imagePath = $this->downloadImage((string) ($meal['strMealThumb'] ?? ''), (string) $meal['idMeal']);
        $dietType = $this->inferDietType($ingredientPayload);

        if ($imagePath === null) {
            throw new \RuntimeException('Seedable meals must include a downloadable image.');
        }

        $recipe = Recipe::query()->updateOrCreate(
            [
                'user_id' => $owner->id,
                'title' => (string) $meal['strMeal'],
            ],
            [
                'image_path' => $imagePath,
                'instructions' => trim((string) ($meal['strInstructions'] ?? '')),
                'preparation_time' => $this->inferPreparationTime($ingredientPayload),
                'servings' => min(8, max(1, intdiv(max(count($ingredientPayload), 2), 1))),
                'difficulty' => $this->inferDifficulty($ingredientPayload, (string) ($meal['strInstructions'] ?? '')),
                'calorie_intake' => $this->inferCalories($ingredientPayload),
                'status' => RecipeStatus::Accepted,
                'diet_type' => $dietType,
                'meal' => $this->inferMeal((string) ($meal['strCategory'] ?? ''), (string) ($meal['strMeal'] ?? '')),
            ],
        );

        $recipe->ingredients()->sync($this->syncIngredients($ingredientPayload));
        $recipe->tools()->sync($this->syncTools((string) ($meal['strInstructions'] ?? ''), (string) ($meal['strMeal'] ?? '')));

        return $recipe;
    }

    /**
     * @param  array<string, mixed>  $meal
     */
    private function isSeedableMeal(array $meal): bool
    {
        $title = trim((string) ($meal['strMeal'] ?? ''));
        $instructions = trim((string) ($meal['strInstructions'] ?? ''));
        $imageUrl = trim((string) ($meal['strMealThumb'] ?? ''));
        $ingredientPayload = $this->ingredientPayload($meal);

        return $title !== ''
            && mb_strlen($title) >= 4
            && $instructions !== ''
            && str_word_count($instructions) >= 8
            && $imageUrl !== ''
            && count($ingredientPayload) >= 3;
    }

    /**
     * @param  array<string, mixed>  $meal
     * @return list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>
     */
    private function ingredientPayload(array $meal): array
    {
        $ingredients = [];

        foreach (range(1, 20) as $index) {
            $title = trim((string) ($meal['strIngredient'.$index] ?? ''));
            $measure = trim((string) ($meal['strMeasure'.$index] ?? ''));

            if ($title === '') {
                continue;
            }

            $ingredients[] = [
                'title' => $title,
                'measurement' => $this->parseMeasurement($measure),
                'quantity' => $this->parseQuantity($measure),
                'category' => $this->inferIngredientCategory($title),
                'importance' => true,
            ];
        }

        return $ingredients;
    }

    /**
     * @param  list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>  $ingredientPayload
     * @return array<int, array{quantity: int, measurement: string, importance: bool}>
     */
    private function syncIngredients(array $ingredientPayload): array
    {
        return collect($ingredientPayload)
            ->mapWithKeys(function (array $ingredient): array {
                $product = Product::query()
                    ->where('title', $ingredient['title'])
                    ->whereNotNull('ingredient_id')
                    ->with('ingredient')
                    ->first();

                if ($product === null || $product->ingredient === null) {
                    $ingredientModel = Ingredient::query()->create([
                        'category' => $ingredient['category'],
                    ]);

                    $product = Product::query()->create([
                        'title' => $ingredient['title'],
                        'quantity' => 1,
                        'measurement' => Measurement::UNIT,
                        'ingredient_id' => $ingredientModel->id,
                    ]);

                    $product->load('ingredient');
                }

                return [
                    $product->ingredient->id => [
                        'quantity' => $ingredient['quantity'],
                        'measurement' => $ingredient['measurement']->value,
                        'importance' => $ingredient['importance'],
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return list<int>
     */
    private function syncTools(string $instructions, string $title): array
    {
        $toolDefinitions = collect([
            ['keyword' => 'oven', 'title' => 'Oven', 'type' => ToolType::BAKING],
            ['keyword' => 'pan', 'title' => 'Pan', 'type' => ToolType::COOKING],
            ['keyword' => 'pot', 'title' => 'Pot', 'type' => ToolType::COOKING],
            ['keyword' => 'knife', 'title' => 'Chef Knife', 'type' => ToolType::CUTTING],
            ['keyword' => 'blend', 'title' => 'Blender', 'type' => ToolType::ELECTRONIC_DEVICE],
            ['keyword' => 'mix', 'title' => 'Mixing Bowl', 'type' => ToolType::MIXING],
            ['keyword' => 'whisk', 'title' => 'Whisk', 'type' => ToolType::MIXING],
        ]);
        $haystack = str($title.' '.$instructions)->lower()->value();
        $toolIds = $toolDefinitions
            ->filter(fn (array $tool): bool => str_contains($haystack, $tool['keyword']))
            ->push(['keyword' => 'default', 'title' => 'Cooking Pot', 'type' => ToolType::COOKING])
            ->unique('title')
            ->map(function (array $tool): int {
                $product = Product::query()
                    ->where('title', $tool['title'])
                    ->whereNotNull('tool_id')
                    ->with('tool')
                    ->first();

                if ($product === null || $product->tool === null) {
                    $toolModel = Tool::query()->create([
                        'type' => $tool['type'],
                    ]);

                    $product = Product::query()->create([
                        'title' => $tool['title'],
                        'quantity' => 1,
                        'measurement' => Measurement::UNIT,
                        'tool_id' => $toolModel->id,
                    ]);

                    $product->load('tool');
                }

                return $product->tool->id;
            })
            ->values()
            ->all();

        return $toolIds;
    }

    /**
     * @param  list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>  $ingredientPayload
     */
    private function inferDietType(array $ingredientPayload): DietType
    {
        $categories = collect($ingredientPayload)->pluck('category');

        if (! $categories->contains(IngredientCategory::MEAT) && ! $categories->contains(IngredientCategory::DAIRY) && ! $categories->contains(IngredientCategory::FISH_PRODUCT)) {
            return DietType::Vegan;
        }

        if (! $categories->contains(IngredientCategory::MEAT) && ! $categories->contains(IngredientCategory::FISH_PRODUCT)) {
            return DietType::Vegetarian;
        }

        if (! $categories->contains(IngredientCategory::DAIRY) && ! $categories->contains(IngredientCategory::WHEAT_PRODUCT) && ! $categories->contains(IngredientCategory::FLOUR_PRODUCT)) {
            return DietType::Paleo;
        }

        if (! $categories->contains(IngredientCategory::WHEAT_PRODUCT) && ! $categories->contains(IngredientCategory::FLOUR_PRODUCT) && $categories->contains(IngredientCategory::MEAT)) {
            return DietType::Keto;
        }

        return DietType::IntermittentFasting;
    }

    private function inferMeal(string $category, string $title): Meal
    {
        $haystack = str($category.' '.$title)->lower()->value();

        if (str_contains($haystack, 'breakfast')) {
            return Meal::Breakfast;
        }

        if (str_contains($haystack, 'salad') || str_contains($haystack, 'lunch')) {
            return Meal::Lunch;
        }

        return Meal::Dinner;
    }

    /**
     * @param  list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>  $ingredientPayload
     */
    private function inferDifficulty(array $ingredientPayload, string $instructions): RecipeDifficulty
    {
        $score = count($ingredientPayload) + (int) ceil(str_word_count($instructions) / 120);

        return match (true) {
            $score <= 8 => RecipeDifficulty::Easy,
            $score <= 14 => RecipeDifficulty::Medium,
            default => RecipeDifficulty::Hard,
        };
    }

    /**
     * @param  list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>  $ingredientPayload
     */
    private function inferCalories(array $ingredientPayload): int
    {
        $base = 120 + (count($ingredientPayload) * 55);
        $proteinBonus = collect($ingredientPayload)
            ->filter(fn (array $ingredient): bool => in_array($ingredient['category'], [IngredientCategory::MEAT, IngredientCategory::FISH_PRODUCT], true))
            ->count() * 90;

        return $base + $proteinBonus;
    }

    /**
     * @param  list<array{title: string, measurement: Measurement, quantity: int, category: IngredientCategory, importance: bool}>  $ingredientPayload
     */
    private function inferPreparationTime(array $ingredientPayload): string
    {
        return (15 + (count($ingredientPayload) * 5)).' minutes';
    }

    private function parseMeasurement(string $measure): Measurement
    {
        $normalizedMeasure = str($measure)->lower()->value();

        return match (true) {
            str_contains($normalizedMeasure, 'kg') => Measurement::KG,
            str_contains($normalizedMeasure, 'ml') => Measurement::ML,
            preg_match('/(^|\s)l($|\s)/', $normalizedMeasure) === 1 => Measurement::L,
            str_contains($normalizedMeasure, 'g') => Measurement::G,
            default => Measurement::UNIT,
        };
    }

    private function parseQuantity(string $measure): int
    {
        if (preg_match('/(?P<numerator>\d+)\s*\/\s*(?P<denominator>\d+)/', $measure, $matches) === 1) {
            return max(1, (int) ceil(((int) $matches['numerator']) / max(1, (int) $matches['denominator'])));
        }

        if (preg_match('/\d+(?:\.\d+)?/', $measure, $matches) === 1) {
            return max(1, (int) ceil((float) $matches[0]));
        }

        return 1;
    }

    private function inferIngredientCategory(string $title): IngredientCategory
    {
        $normalizedTitle = str($title)->lower()->value();

        return match (true) {
            str_contains($normalizedTitle, 'beef'), str_contains($normalizedTitle, 'chicken'), str_contains($normalizedTitle, 'pork'), str_contains($normalizedTitle, 'lamb') => IngredientCategory::MEAT,
            str_contains($normalizedTitle, 'salmon'), str_contains($normalizedTitle, 'tuna'), str_contains($normalizedTitle, 'fish'), str_contains($normalizedTitle, 'shrimp') => IngredientCategory::FISH_PRODUCT,
            str_contains($normalizedTitle, 'milk'), str_contains($normalizedTitle, 'cheese'), str_contains($normalizedTitle, 'cream'), str_contains($normalizedTitle, 'butter'), str_contains($normalizedTitle, 'yogurt') => IngredientCategory::DAIRY,
            str_contains($normalizedTitle, 'rice'), str_contains($normalizedTitle, 'bread'), str_contains($normalizedTitle, 'pasta'), str_contains($normalizedTitle, 'noodle') => IngredientCategory::WHEAT_PRODUCT,
            str_contains($normalizedTitle, 'flour'), str_contains($normalizedTitle, 'tortilla'), str_contains($normalizedTitle, 'pastry') => IngredientCategory::FLOUR_PRODUCT,
            str_contains($normalizedTitle, 'pepper'), str_contains($normalizedTitle, 'salt'), str_contains($normalizedTitle, 'paprika'), str_contains($normalizedTitle, 'spice') => IngredientCategory::SEASONING,
            str_contains($normalizedTitle, 'almond'), str_contains($normalizedTitle, 'walnut'), str_contains($normalizedTitle, 'cashew') => IngredientCategory::NUT,
            str_contains($normalizedTitle, 'seed'), str_contains($normalizedTitle, 'sesame') => IngredientCategory::SEED,
            str_contains($normalizedTitle, 'berry'), str_contains($normalizedTitle, 'blueberry'), str_contains($normalizedTitle, 'strawberry') => IngredientCategory::BERRY,
            str_contains($normalizedTitle, 'mushroom') => IngredientCategory::MUSHROOM,
            str_contains($normalizedTitle, 'apple'), str_contains($normalizedTitle, 'banana'), str_contains($normalizedTitle, 'orange'), str_contains($normalizedTitle, 'lemon') => IngredientCategory::FRUIT,
            default => IngredientCategory::VEGETABLE,
        };
    }

    private function downloadImage(string $imageUrl, string $mealId): ?string
    {
        if ($imageUrl === '') {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get($imageUrl);

            if (! $response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
            $imagePath = 'recipes/'.$mealId.'.'.$extension;

            Storage::disk('public')->put($imagePath, $response->body());

            return $imagePath;
        } catch (Throwable) {
            return null;
        }
    }
}
