<?php

namespace App\Http\Controllers\Personalization;

use App\Enums\PreferenceStatus;
use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyPreferenceRequest;
use App\Http\Requests\DislikeSuggestionRequest;
use App\Http\Requests\LikeSuggestionRequest;
use App\Http\Requests\StorePreferenceRequest;
use App\Models\Preference;
use App\Models\Recipe;
use App\Models\User;
use App\Services\recipe_suggestion_service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class personalization_controller extends Controller
{
    public function preferences(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Personalization/preferences_page', [
            'preferences' => $user->preferences()
                ->with(['recipe.ingredients.product', 'recipe.tools.product'])
                ->latest('generation_date')
                ->get()
                ->map(fn (Preference $preference): array => $this->serializePreference($preference)),
        ]);
    }

    public function createPreference(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Personalization/add_preference_page', [
            'recipes' => Recipe::query()
                ->whereNotIn('id', $user->preferences()->select('recipe_id'))
                ->where('status', RecipeStatus::Accepted->value)
                ->orderBy('title')
                ->get()
                ->map(fn (Recipe $recipe): array => $this->serializeRecipe($recipe)),
            'statuses' => collect([PreferenceStatus::Liked, PreferenceStatus::Disliked])
                ->map(fn (PreferenceStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
        ]);
    }

    public function storePreference(StorePreferenceRequest $request): RedirectResponse
    {
        $request->user()->preferences()->create([
            ...$request->validated(),
            'generation_date' => now()->toDateString(),
        ]);

        return redirect(route('preferences.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Preference created successfully.',
            ]);
    }

    public function destroyPreference(DestroyPreferenceRequest $request, Preference $preference): RedirectResponse
    {
        $request->validated();

        Preference::query()->whereKey($preference->getKey())->delete();

        return redirect(route('preferences.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Preference removed successfully.',
            ]);
    }

    public function suggestions(Request $request, recipe_suggestion_service $recipeSuggestionService): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->renderSuggestionPage(
            $user,
            $recipeSuggestionService->generateSuggestions($user, $this->loadSuggestionContext($user)),
            $recipeSuggestionService,
        );
    }

    public function likeSuggestion(LikeSuggestionRequest $request, Preference $preference, recipe_suggestion_service $recipeSuggestionService): Response
    {
        $request->validated();

        return $this->storeSuggestionDecision(
            $request,
            $preference,
            PreferenceStatus::Liked,
            'Recipe added to liked preferences.',
            $recipeSuggestionService,
        );
    }

    public function dislikeSuggestion(DislikeSuggestionRequest $request, Preference $preference, recipe_suggestion_service $recipeSuggestionService): Response
    {
        $request->validated();

        return $this->storeSuggestionDecision(
            $request,
            $preference,
            PreferenceStatus::Disliked,
            'Recipe added to disliked preferences.',
            $recipeSuggestionService,
        );
    }

    private function storeSuggestionDecision(
        Request $request,
        Preference $preference,
        PreferenceStatus $status,
        string $message,
        recipe_suggestion_service $recipeSuggestionService,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $preference->forceFill([
            'preference_status' => $status,
            'generation_date' => now()->toDateString(),
        ])->save();

        return $this->renderNextSuggestionPage($user, $recipeSuggestionService, [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * @param  array{type: string, message: string}|null  $toast
     */
    private function renderNextSuggestionPage(User $user, recipe_suggestion_service $recipeSuggestionService, ?array $toast = null): Response
    {
        return $this->renderSuggestionPage(
            $user,
            $recipeSuggestionService->removeSuggestion($user),
            $recipeSuggestionService,
            $toast,
        );
    }

    /**
     * @param  array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }|null  $suggestion
     * @param  array{type: string, message: string}|null  $toast
     */
    private function renderSuggestionPage(User $user, ?array $suggestion, recipe_suggestion_service $recipeSuggestionService, ?array $toast = null): Response
    {
        return Inertia::render('Personalization/recipe_suggestion_page', [
            'suggestion' => $this->activateSuggestion($user, $suggestion),
            'remaining_suggestions_count' => $recipeSuggestionService->remainingSuggestionCount($user),
            'flash' => [
                'toast' => $toast,
            ],
        ]);
    }

    /**
     * @param  array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }|null  $suggestion
     * @return array{
     *     id: int,
     *     preference_status: string,
     *     status_label: string,
     *     generation_date: string,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }|null
     */
    private function activateSuggestion(User $user, ?array $suggestion): ?array
    {
        if ($suggestion === null) {
            return null;
        }

        $preference = $this->resolveAwaitingSuggestionPreference($user, $suggestion['recipe_id']);

        return [
            'id' => $preference->id,
            'preference_status' => PreferenceStatus::Awaiting->value,
            'status_label' => PreferenceStatus::Awaiting->label(),
            'generation_date' => $preference->generation_date?->toDateString() ?? now()->toDateString(),
            'score' => $suggestion['score'],
            'missing_ingredients_count' => $suggestion['missing_ingredients_count'],
            'matched_tools_count' => $suggestion['matched_tools_count'],
            'available_ingredients_count' => $suggestion['available_ingredients_count'],
            'recipe' => $suggestion['recipe'],
        ];
    }

    private function resolveAwaitingSuggestionPreference(User $user, int $recipeId): Preference
    {
        $existingPreference = $user->preferences()
            ->where('preference_status', PreferenceStatus::Awaiting->value)
            ->where('recipe_id', $recipeId)
            ->first();

        if ($existingPreference instanceof Preference) {
            return $existingPreference;
        }

        $user->preferences()
            ->where('preference_status', PreferenceStatus::Awaiting->value)
            ->delete();

        return $user->preferences()->create([
            'recipe_id' => $recipeId,
            'preference_status' => PreferenceStatus::Awaiting,
            'generation_date' => now()->toDateString(),
        ]);
    }

    private function recipeImageUrl(Recipe $recipe): ?string
    {
        if ($recipe->image_path === null) {
            return null;
        }

        return asset('storage/'.$recipe->image_path);
    }

    private function serializePreference(Preference $preference): array
    {
        return [
            'id' => $preference->id,
            'preference_status' => $preference->preference_status->value,
            'status_label' => $preference->preference_status->label(),
            'generation_date' => $preference->generation_date?->toDateString(),
            'recipe' => $this->serializeRecipe($preference->recipe),
        ];
    }

    /**
     * @return array{
     *     ingredient_ids: list<int>,
     *     tool_ids: list<int>,
     *     preferred_diet_types: list<string>,
     *     disliked_ingredient_ids: list<int>,
     *     recipes: list<Recipe>
     * }
     */
    private function loadSuggestionContext(User $user): array
    {
        $tasks = [
            'ingredient_ids' => static fn (): array => self::ingredientIdsForUser($user),
            'tool_ids' => static fn (): array => self::toolIdsForUser($user),
            'preferred_diet_types' => static fn (): array => self::preferredDietTypesForUser($user),
            'disliked_ingredient_ids' => static fn (): array => self::dislikedIngredientIdsForUser($user),
            'recipes' => static fn (): array => self::availableSuggestionRecipesForUser($user),
        ];

        return Concurrency::run($tasks);
    }

    /**
     * @return list<int>
     */
    private static function ingredientIdsForUser(User $user): array
    {
        return $user->ingredients()
            ->pluck('ingredients.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function toolIdsForUser(User $user): array
    {
        return $user->tools()
            ->pluck('tools.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function preferredDietTypesForUser(User $user): array
    {
        return Preference::query()
            ->whereBelongsTo($user)
            ->where('preference_status', PreferenceStatus::Liked->value)
            ->with('recipe:id,diet_type')
            ->get()
            ->map(static fn (Preference $preference): ?string => $preference->recipe?->diet_type?->value)
            ->filter()
            ->values()
            ->unique()
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function dislikedIngredientIdsForUser(User $user): array
    {
        return Preference::query()
            ->whereBelongsTo($user)
            ->where('preference_status', PreferenceStatus::Disliked->value)
            ->with('recipe.ingredients:id')
            ->get()
            ->flatMap(static fn (Preference $preference): Collection => $preference->recipe?->ingredients->pluck('id') ?? collect())
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->unique()
            ->all();
    }

    /**
     * @return list<Recipe>
     */
    private static function availableSuggestionRecipesForUser(User $user): array
    {
        return Recipe::query()
            ->where('status', RecipeStatus::Accepted->value)
            ->whereNotIn(
                'id',
                Preference::query()
                    ->whereBelongsTo($user)
                    ->whereIn('preference_status', [PreferenceStatus::Liked->value, PreferenceStatus::Disliked->value])
                    ->select('recipe_id'),
            )
            ->with(['ingredients.product', 'tools.product'])
            ->get()
            ->all();
    }

    private function serializeRecipe(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'image_url' => $this->recipeImageUrl($recipe),
            'instructions' => $recipe->instructions,
            'preparation_time' => $recipe->preparation_time,
            'servings' => $recipe->servings,
            'difficulty' => $recipe->difficulty->value,
            'difficulty_label' => $recipe->difficulty->label(),
            'calorie_intake' => $recipe->calorie_intake,
            'status' => $recipe->status->value,
            'status_label' => $recipe->status->label(),
            'diet_type' => $recipe->diet_type->value,
            'diet_type_label' => $recipe->diet_type->label(),
            'meal' => $recipe->meal->value,
            'meal_label' => $recipe->meal->label(),
            'ingredients' => $recipe->relationLoaded('ingredients')
                ? $recipe->ingredients->map(static fn ($ingredient): array => [
                    'id' => $ingredient->id,
                    'title' => $ingredient->product?->title ?? 'Ingredient',
                    'importance' => (bool) $ingredient->pivot?->importance,
                ])->values()->all()
                : [],
            'tools' => $recipe->relationLoaded('tools')
                ? $recipe->tools->map(static fn ($tool): array => [
                    'id' => $tool->id,
                    'title' => $tool->product?->title ?? $tool->type->value,
                ])->values()->all()
                : [],
        ];
    }
}
