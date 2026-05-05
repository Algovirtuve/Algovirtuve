<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class recipe_suggestion_service
{
    private const CACHE_TTL_SECONDS = 86400;

    private const MAX_SUGGESTIONS = 50;

    /**
     * @param  array{
     *     ingredient_ids: list<int>,
     *     tool_ids: list<int>,
     *     preferred_diet_types: list<string>,
     *     disliked_ingredient_ids: list<int>,
     *     recipes: list<Recipe>
     * }  $suggestionContext
     * @return array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }|null
     */
    public function generateSuggestions(User $user, array $suggestionContext): ?array
    {
        $suggestions = $this->getSuggestions($user);

        if ($suggestions === []) {
            $suggestions = $this->cacheSuggestions($user, $suggestionContext);
        }

        return $suggestions[0] ?? null;
    }

    /**
     * @return array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }|null
     */
    public function removeSuggestion(User $user): ?array
    {
        $remainingSuggestions = $this->getSuggestions($user);

        array_shift($remainingSuggestions);

        $this->putSuggestions($user, $remainingSuggestions);

        return $remainingSuggestions[0] ?? null;
    }

    public function remainingSuggestionCount(User $user): int
    {
        return count($this->getSuggestions($user));
    }

    /**
     * @return list<array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }>
     */
    private function getSuggestions(User $user): array
    {
        $suggestions = Cache::get($this->cacheKey($user));

        if (! is_array($suggestions)) {
            return [];
        }

        return collect($suggestions)
            ->filter(static fn (mixed $suggestion): bool => is_array($suggestion))
            ->map(static function (array $suggestion): array {
                $recipe = $suggestion['recipe'] ?? null;

                if (! is_array($recipe)) {
                    return [];
                }

                return [
                    'recipe_id' => (int) ($suggestion['recipe_id'] ?? 0),
                    'score' => (int) ($suggestion['score'] ?? 0),
                    'missing_ingredients_count' => (int) ($suggestion['missing_ingredients_count'] ?? 0),
                    'matched_tools_count' => (int) ($suggestion['matched_tools_count'] ?? 0),
                    'available_ingredients_count' => (int) ($suggestion['available_ingredients_count'] ?? 0),
                    'recipe' => $recipe,
                ];
            })
            ->filter(static fn (array $suggestion): bool => $suggestion !== [] && $suggestion['recipe_id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     ingredient_ids: list<int>,
     *     tool_ids: list<int>,
     *     preferred_diet_types: list<string>,
     *     disliked_ingredient_ids: list<int>,
     *     recipes: list<Recipe>
     * }  $suggestionContext
     * @return list<array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }>
     */
    private function cacheSuggestions(User $user, array $suggestionContext): array
    {
        $rankedRecipes = collect($suggestionContext['recipes'])
            ->map(function (Recipe $recipe) use ($suggestionContext): array {
                $analysis = $this->analyzeRecipe($recipe, $suggestionContext);

                return [
                    'recipe' => $recipe,
                    'score' => $analysis['score'],
                    'missing_ingredients_count' => count($analysis['missing_ingredient_ids']),
                    'matched_tools_count' => count($analysis['matched_tool_ids']),
                    'available_ingredients_count' => count($analysis['available_ingredient_ids']),
                ];
            })
            ->all();

        usort($rankedRecipes, function (array $left, array $right): int {
            $scoreComparison = $right['score'] <=> $left['score'];

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcasecmp($left['recipe']->title, $right['recipe']->title);
        });

        $suggestions = collect($rankedRecipes)
            ->take(self::MAX_SUGGESTIONS)
            ->map(fn (array $rankedRecipe): array => $this->buildCachedSuggestion($rankedRecipe['recipe'], $rankedRecipe))
            ->values()
            ->all();

        $this->putSuggestions($user, $suggestions);

        return $suggestions;
    }

    /**
     * @param  array{
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: Recipe
     * }  $rankedRecipe
     * @return array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }
     */
    private function buildCachedSuggestion(Recipe $recipe, array $rankedRecipe): array
    {
        return [
            'recipe_id' => $recipe->id,
            'score' => $rankedRecipe['score'],
            'missing_ingredients_count' => $rankedRecipe['missing_ingredients_count'],
            'matched_tools_count' => $rankedRecipe['matched_tools_count'],
            'available_ingredients_count' => $rankedRecipe['available_ingredients_count'],
            'recipe' => $this->serializeRecipe($recipe),
        ];
    }

    /**
     * @param array{
     *     ingredient_ids: list<int>,
     *     tool_ids: list<int>,
     *     preferred_diet_types: list<string>,
     *     disliked_ingredient_ids: list<int>
     * } $userContext
     * @return array{
     *     score: int,
     *     available_ingredient_ids: list<int>,
     *     missing_ingredient_ids: list<int>,
     *     matched_tool_ids: list<int>
     * }
     */
    private function analyzeRecipe(Recipe $recipe, array $userContext): array
    {
        $recipeIngredientIds = $recipe->ingredients->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $requiredIngredientIds = $recipe->ingredients
            ->filter(static fn ($ingredient): bool => (bool) $ingredient->pivot?->importance)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $recipeToolIds = $recipe->tools->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($requiredIngredientIds === []) {
            $requiredIngredientIds = $recipeIngredientIds;
        }

        $availableIngredientIds = array_values(array_intersect($recipeIngredientIds, $userContext['ingredient_ids']));
        $missingIngredientIds = array_values(array_diff($requiredIngredientIds, $userContext['ingredient_ids']));
        $matchedToolIds = array_values(array_intersect($recipeToolIds, $userContext['tool_ids']));
        $dislikedIngredientOverlap = array_values(array_intersect($recipeIngredientIds, $userContext['disliked_ingredient_ids']));

        $score = count($availableIngredientIds) + count($matchedToolIds);

        if ($missingIngredientIds === []) {
            $score += 10;
        }

        if ($recipeToolIds === [] || count($matchedToolIds) === count($recipeToolIds)) {
            $score += 5;
        }

        if (count($missingIngredientIds) <= 2) {
            $score += 5;
        }

        if (in_array($recipe->diet_type->value, $userContext['preferred_diet_types'], true)) {
            $score += 6;
        }

        $score -= count($dislikedIngredientOverlap) * 10;

        return [
            'score' => $score,
            'available_ingredient_ids' => $availableIngredientIds,
            'missing_ingredient_ids' => $missingIngredientIds,
            'matched_tool_ids' => $matchedToolIds,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     image_url: ?string,
     *     instructions: string,
     *     preparation_time: string,
     *     servings: int,
     *     difficulty: string,
     *     difficulty_label: string,
     *     calorie_intake: int,
     *     status: string,
     *     status_label: string,
     *     diet_type: string,
     *     diet_type_label: string,
     *     meal: string,
     *     meal_label: string,
     *     ingredients: list<array{id: int, title: string, importance: bool}>,
     *     tools: list<array{id: int, title: string}>
     * }
     */
    private function serializeRecipe(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'title' => $recipe->title,
            'image_url' => $recipe->image_path === null ? null : asset('storage/'.$recipe->image_path),
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
            'ingredients' => $recipe->ingredients->map(static fn ($ingredient): array => [
                'id' => $ingredient->id,
                'title' => $ingredient->product?->title ?? 'Ingredient',
                'importance' => (bool) $ingredient->pivot?->importance,
            ])->values()->all(),
            'tools' => $recipe->tools->map(static fn ($tool): array => [
                'id' => $tool->id,
                'title' => $tool->product?->title ?? $tool->type->value,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<array{
     *     recipe_id: int,
     *     score: int,
     *     missing_ingredients_count: int,
     *     matched_tools_count: int,
     *     available_ingredients_count: int,
     *     recipe: array<string, mixed>
     * }>  $suggestions
     */
    private function putSuggestions(User $user, array $suggestions): void
    {
        Cache::put($this->cacheKey($user), $suggestions, now()->addSeconds(self::CACHE_TTL_SECONDS));
    }

    private function cacheKey(User $user): string
    {
        return 'user:'.$user->id.':recipe_suggestions';
    }
}
