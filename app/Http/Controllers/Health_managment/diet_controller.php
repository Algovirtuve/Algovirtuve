<?php

namespace App\Http\Controllers\Health_managment;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\DietPlan;
use App\Models\DietPlanMacroelement;
use App\Models\Macroelement;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class diet_controller extends Controller
{
    private const SESSION_TEMP_MACROS = 'diet_plan_generation.temp.macroelements';

    private const SESSION_TEMP_TYPE = 'diet_plan_generation.temp.diet_type';

    private const SESSION_TEMP_CALORIE = 'diet_plan_generation.temp.day_calorie_limit';

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
                'temp' => [
                    'macroelements' => $request->session()->get(self::SESSION_TEMP_MACROS, []),
                    'diet_type' => $request->session()->get(self::SESSION_TEMP_TYPE),
                    'day_calorie_limit' => $request->session()->get(self::SESSION_TEMP_CALORIE),
                ],
            ],
        ));
    }

    public function onGenerateClick(Request $request): Response
    {
        // Backwards-compatible entry point (older UI). The new flow uses generateDietPlan().
        return $this->generateDietPlan($request);
    }

    public function insertToTempMacros(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'macroelements' => ['required', 'array', 'min:1'],
            'macroelements.*.id' => ['required', 'integer', 'exists:macroelements,id'],
            'macroelements.*.target_kcal' => ['required', 'integer', 'min:1'],
        ]);

        $request->session()->put(self::SESSION_TEMP_MACROS, $this->formatSelectedMacroelements($data['macroelements']));

        return redirect(route('diet.generate.view', absolute: false));
    }

    public function insertToTempType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'diet_type' => ['required', 'in:'.implode(',', DietType::all())],
        ]);

        $request->session()->put(self::SESSION_TEMP_TYPE, $data['diet_type']);

        return redirect(route('diet.generate.view', absolute: false));
    }

    public function insertToTempCalorie(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'day_calorie_limit' => ['required', 'integer', 'min:1'],
        ]);

        $request->session()->put(self::SESSION_TEMP_CALORIE, (int) $data['day_calorie_limit']);

        return redirect(route('diet.generate.view', absolute: false));
    }

    public function generateDietPlan(Request $request): Response
    {
        $payload = [
            'macroelements' => $request->input('macroelements', $request->session()->get(self::SESSION_TEMP_MACROS, [])),
            'diet_type' => $request->input('diet_type', $request->session()->get(self::SESSION_TEMP_TYPE)),
            'day_calorie_limit' => $request->input('day_calorie_limit', $request->session()->get(self::SESSION_TEMP_CALORIE)),
        ];

        $data = validator($payload, [
            'macroelements' => ['required', 'array', 'min:1'],
            'macroelements.*.id' => ['required', 'integer', 'exists:macroelements,id'],
            'macroelements.*.target_kcal' => ['required', 'integer', 'min:1'],
            'diet_type' => ['required', 'in:'.implode(',', DietType::all())],
            'day_calorie_limit' => ['required', 'integer', 'min:1'],
        ])->validate();

        $post = $this->createPost($data);
        $this->savePost($post, $data);

        $recommendations = $this->processTask($post, $data);
        $this->saveRecommendations($post, $recommendations);

        $mealCalorieLimits = $this->calculateCalorieLimits((int) $data['day_calorie_limit']);

        // Clear wizard session state after successful generation.
        $request->session()->forget([
            self::SESSION_TEMP_MACROS,
            self::SESSION_TEMP_TYPE,
            self::SESSION_TEMP_CALORIE,
        ]);

        return Inertia::render('Health_managment/diet_plan_page', [
            'generatedPlan' => [
                'id' => $post->id,
                'diet_type' => $post->diet_type,
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
     */
    protected function createPost(array $data): DietPlan
    {
        return new DietPlan([
            'diet_type' => $data['diet_type'],
        ]);
    }

    /**
     * @param  array{
     *     macroelements: array<int, array{id: int, target_kcal: int}>,
     *     diet_type: string,
     *     day_calorie_limit: int
     * }  $data
     */
    protected function savePost(DietPlan $post, array $data): void
    {
        $post->save();

        // Store selected macroelements on the pivot table via the DietPlanMacroelement model.
        DietPlanMacroelement::query()->where('diet_plan_id', $post->id)->delete();

        $macroelementIds = collect($data['macroelements'])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        DietPlanMacroelement::query()->insert(
            $macroelementIds
                ->map(static fn (int $macroelementId): array => [
                    'diet_plan_id' => $post->id,
                    'macroelement_id' => $macroelementId,
                ])
                ->all(),
        );
    }

    /**
     * @param  array{
     *     macroelements: array<int, array{id: int, target_kcal: int}>,
     *     diet_type: string,
     *     day_calorie_limit: int
     * }  $data
     * @return array<string, array<int, array{score: int, recipe: array<string, mixed>}>>
     */
    protected function processTask(DietPlan $post, array $data): array
    {
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

        $breakfastRecipes = $this->filterRecipesByMeal($recipesByDietType, Meal::Breakfast);
        $lunchRecipes = $this->filterRecipesByMeal($recipesByDietType, Meal::Lunch);
        $dinnerRecipes = $this->filterRecipesByMeal($recipesByDietType, Meal::Dinner);

        $macrosDescending = $this->sortMacrosDescending($data['macroelements']);

        $breakfastCandidates = $this->seedCandidates($breakfastRecipes, $dietType, $mealCalorieLimits['breakfast']);
        $lunchCandidates = $this->seedCandidates($lunchRecipes, $dietType, $mealCalorieLimits['lunch']);
        $dinnerCandidates = $this->seedCandidates($dinnerRecipes, $dietType, $mealCalorieLimits['dinner']);

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

        $this->updateStatus($post, 'generated');
        $this->checkStatus($post);

        return $recommendations;
    }

    protected function updateStatus(DietPlan $post, string $status): void
    {
        // The original diagram has a status update step; the current schema does not store it.
    }

    protected function checkStatus(DietPlan $post): void
    {
        // The diagram's status check is modeled as a synchronous controller step here.
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
    private function filterRecipesByMeal(Collection $recipes, Meal $meal): Collection
    {
        return $recipes
            ->filter(static fn (Recipe $recipe): bool => $recipe->meal === $meal)
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

    /**
     * @param  Collection<int, Recipe>  $recipes
     * @return Collection<int, array{recipe: Recipe, score: int}>
     */
    private function seedCandidates(Collection $recipes, string $dietType, int $mealCalorieLimit): Collection
    {
        return $recipes
            ->map(function (Recipe $recipe) use ($dietType, $mealCalorieLimit): array {
                $score = $this->scoreRecipeCaloriesAndDietType($recipe, $dietType, $mealCalorieLimit);

                return [
                    'recipe' => $recipe,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();
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

    /**
     * @param  array{
     *     macroelements: array<int, array{id: int, target_kcal: int}>,
     *     diet_type: string,
     *     day_calorie_limit: int
     * }  $data
     * @param  array<string, array<int, array{score: int, recipe: array<string, mixed>}>>  $recommendations
     */
    private function saveRecommendations(DietPlan $post, array $recommendations): void
    {
        $recipeIds = collect($recommendations)
            ->flatMap(static fn (array $mealRecommendations): Collection => collect($mealRecommendations)->pluck('recipe.id'))
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $post->recipes()->syncWithoutDetaching($recipeIds);
    }
}
