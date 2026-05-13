<?php

namespace App\Http\Controllers\Health_managment;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\Measurement;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\DietPlan;
use App\Models\DietPlanMacroelement;
use App\Models\Macroelement;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class diet_controller extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Health_managment/diet_page', $this->buildViewData());
    }

    public function showGeneratePostPage(Request $request): Response
    {
        return $this->viewGenerateDietPlan($request);
    }

    public function viewGenerateDietPlan(Request $request): Response
    {
        return Inertia::render('Health_managment/diet_plan_generation_page', array_merge(
            $this->buildViewData(),
            [
                'tempState' => [
                    'macroelements' => [],
                    'diet_type' => null,
                    'day_calorie_limit' => null,
                ],
            ],
        ));
    }

    public function onGenerateClick(Request $request): Response
    {
        // Backwards-compatible entry point (older UI). The new flow uses generateDietPlan().
        return $this->generateDietPlan($request);
    }

    public function generateDietPlan(Request $request): Response
    {
        $payload = [
            'macroelements' => $request->input('macroelements', []),
            'diet_type' => $request->input('diet_type'),
            'day_calorie_limit' => $request->input('day_calorie_limit'),
        ];

        $data = validator($payload, [
            'macroelements' => ['required', 'array', 'min:1'],
            'macroelements.*.id' => ['required', 'integer', 'exists:macroelements,id'],
            'macroelements.*.target_kcal' => ['required', 'integer', 'min:1'],
            'diet_type' => ['required', 'in:'.implode(',', DietType::all())],
            'day_calorie_limit' => ['required', 'integer', 'min:1'],
        ])->validate();

        $dietType = (string) $data['diet_type'];
        $dayCalorieLimit = (int) $data['day_calorie_limit'];

        $recipesByDietType = Recipe::query()
            ->with(['ingredients.macroelements', 'tools'])
            ->where('diet_type', $dietType)
            ->get();

        if ($recipesByDietType->isEmpty()) {
            $recipesByDietType = Recipe::query()
                ->with(['ingredients.macroelements', 'tools'])
                ->get();
        }

        $mealCalorieLimits = $this->calculateCalorieLimits($dayCalorieLimit);

        $breakfastCandidates = $this->filterRecipesByMeal($recipesByDietType, Meal::Breakfast, $mealCalorieLimits['breakfast']);
        $lunchCandidates = $this->filterRecipesByMeal($recipesByDietType, Meal::Lunch, $mealCalorieLimits['lunch']);
        $dinnerCandidates = $this->filterRecipesByMeal($recipesByDietType, Meal::Dinner, $mealCalorieLimits['dinner']);

        $dietPlan = insert(DietPlan::class, ['diet_type' => $dietType]);

        foreach ($data['macroelements'] as $macro) {
            insert(DietPlanMacroelement::class, [
                'diet_plan_id' => $dietPlan->id,
                'macroelement_id' => $macro['id'],
                'quantity' => $macro['target_kcal'],
                'measurement' => Measurement::G->value,
            ]);
        }

        $macrosDescending = $this->sortMacrosDescending($data['macroelements']);

        foreach ($macrosDescending as $macro) {
            $breakfastCandidates = $this->filterBreakfastByMacro($breakfastCandidates, (int) $macro['id'], (int) $macro['target_kcal']);
            $lunchCandidates = $this->filterLunchByMacro($lunchCandidates, (int) $macro['id'], (int) $macro['target_kcal']);
            $dinnerCandidates = $this->filterDinnerByMacro($dinnerCandidates, (int) $macro['id'], (int) $macro['target_kcal']);
        }

        $recommendations = [
            Meal::Breakfast->value => $this->pickTopRecipes($breakfastCandidates),
            Meal::Lunch->value => $this->pickTopRecipes($lunchCandidates),
            Meal::Dinner->value => $this->pickTopRecipes($dinnerCandidates),
        ];

        return Inertia::render('Health_managment/diet_plan_page', [
            'generatedPlan' => [
                'id' => $dietPlan->id,
                'diet_type' => $dietPlan->diet_type,
                'day_calorie_limit' => (int) $data['day_calorie_limit'],
                'meal_calorie_limits' => $mealCalorieLimits,
                'selected_macroelements' => $this->formatSelectedMacroelements($data['macroelements']),
            ],
            'recommendations' => $recommendations,
            'flash' => [
                'toast' => [
                    'type' => 'success',
                    'message' => 'Diet plan generated successfully.',
                ],
            ],
        ]);
    }

    /**
     * @return array{macroelements: array<int, array{id: int, title: string, measurement: string}>, dietTypes: array<int, array{value: string, label: string}>, meals: array<int, array{value: string, label: string}>}
     */
    private function buildViewData(): array
    {
        return [
            'macroelements' => Macroelement::query()
                ->orderBy('title')
                ->get()
                ->map(static fn (Macroelement $macroelement): array => [
                    'id' => $macroelement->id,
                    'title' => $macroelement->title,
                    'measurement' => $macroelement->measurement->value,
                ])
                ->values()
                ->all(),
            'dietTypes' => collect(DietType::cases())
                ->map(static fn (DietType $dietType): array => [
                    'value' => $dietType->value,
                    'label' => $dietType->label(),
                ])
                ->values()
                ->all(),
            'meals' => collect(Meal::cases())
                ->map(static fn (Meal $meal): array => [
                    'value' => $meal->value,
                    'label' => $meal->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     macroelements: array<int, array{id: int, target_kcal: int}>,
     *     diet_type: string,
     *     day_calorie_limit: int
     * }  $data
     * @return array<int, array{score: int, recipe: array<string, mixed>}>
     */
    /**
     * @return Collection<int, Recipe>
     */
    private function filterRecipesByMeal(Collection $recipes, Meal $meal, int $mealCalorieLimit): Collection
    {
        $filtered = $recipes
            ->filter(static fn (Recipe $recipe): bool => $recipe->meal === $meal)
            ->values();

        return $filtered
            ->map(function (Recipe $recipe) use ($meal, $mealCalorieLimit): array {
                $score = $this->scoreRecipeCaloriesAndDietType($recipe, $meal->value, $mealCalorieLimit);

                return [
                    'recipe' => $recipe,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @return array{breakfast: int, lunch: int, dinner: int}
     */
    private function calculateCalorieLimits(int $dayCalorieLimit): array
    {
        $breakfast = (int) round($dayCalorieLimit * 0.2);
        $lunch = (int) round($dayCalorieLimit * 0.5);
        $dinner = max(1, $dayCalorieLimit - $breakfast - $lunch);

        return [
            Meal::Breakfast->value => $breakfast,
            Meal::Lunch->value => $lunch,
            Meal::Dinner->value => $dinner,
        ];
    }

    /**
     * @param  array<int, array{id: int, target_kcal: int}>  $macroelements
     * @return array<int, array{id: int, target_kcal: int}>
     */
    private function sortMacrosDescending(array $macroelements): array
    {
        return collect($macroelements)
            ->sortByDesc(static fn (array $macro): int => (int) $macro['target_kcal'])
            ->values()
            ->all();
    }

    private function filterBreakfastByMacro(Collection $candidates, int $macroelementId, int $targetKcal): Collection
    {
        return $this->filterMealCandidatesByMacro($candidates, $macroelementId, $targetKcal);
    }

    private function filterLunchByMacro(Collection $candidates, int $macroelementId, int $targetKcal): Collection
    {
        return $this->filterMealCandidatesByMacro($candidates, $macroelementId, $targetKcal);
    }

    private function filterDinnerByMacro(Collection $candidates, int $macroelementId, int $targetKcal): Collection
    {
        return $this->filterMealCandidatesByMacro($candidates, $macroelementId, $targetKcal);
    }

    private function filterMealCandidatesByMacro(Collection $candidates, int $macroelementId, int $targetKcal): Collection
    {
        $updated = $candidates
            ->map(function (array $candidate) use ($macroelementId, $targetKcal): array {
                /** @var Recipe $recipe */
                $recipe = $candidate['recipe'];
                $score = (int) $candidate['score'];

                $recipeMacroTotals = $this->recipeMacroTotals($recipe);
                $actual = $recipeMacroTotals[$macroelementId] ?? 0;

                $score += max(0, 75 - abs($actual - $targetKcal));

                return [
                    'recipe' => $recipe,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();

        return $updated->take(30)->values();
    }

    /**
     * @return array<int, array{score: int, recipe: array<string, mixed>}>
     */
    private function pickTopRecipes(Collection $candidates): array
    {
        return $candidates
            ->take(3)
            ->map(static function (array $candidate): array {
                /** @var Recipe $recipe */
                $recipe = $candidate['recipe'];

                return [
                    'score' => (int) $candidate['score'],
                    'recipe' => RecipeResource::make($recipe)->resolve(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     macroelements: array<int, array{id: int, target_kcal: int}>,
     *     diet_type: string,
     *     day_calorie_limit: int
     * }  $data
     */
    private function scoreRecipeCaloriesAndDietType(Recipe $recipe, string $dietType, int $mealTarget): int
    {
        $score = max(0, 250 - abs(((int) $recipe->calorie_intake) - $mealTarget));

        if ($recipe->diet_type->value === $dietType) {
            $score += 100;
        }

        return $score;
    }

    /**
     * @return array<int, int>
     */
    private function recipeMacroTotals(Recipe $recipe): array
    {
        return $recipe->ingredients
            ->flatMap(static function ($ingredient): Collection {
                return $ingredient->macroelements->map(static function ($macroelement): array {
                    return [
                        'macroelement_id' => (int) $macroelement->id,
                        'quantity' => (int) $macroelement->pivot?->quantity,
                    ];
                });
            })
            ->groupBy('macroelement_id')
            ->map(static fn (Collection $items): int => $items->sum('quantity'))
            ->all();
    }

    /**
     * @param  array<int, array{id: int, target_kcal: int}>  $macroelements
     * @return array<int, array{id: int, target_kcal: int}>
     */
    private function formatSelectedMacroelements(array $macroelements): array
    {
        return collect($macroelements)
            ->map(static fn (array $macroelement): array => [
                'id' => (int) $macroelement['id'],
                'target_kcal' => (int) $macroelement['target_kcal'],
            ])
            ->values()
            ->all();
    }

}
