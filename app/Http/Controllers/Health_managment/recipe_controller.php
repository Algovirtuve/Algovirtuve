<?php

namespace App\Http\Controllers\Health_managment;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Request as RecipeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class recipe_controller extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Health_managment/recipe_page', [
            'recipes' => $user->createdRecipes()
                ->latest()
                ->get()
                ->map(fn (Recipe $recipe): array => [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
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
                ]),
            'difficulties' => collect(RecipeDifficulty::cases())
                ->map(fn (RecipeDifficulty $difficulty): array => [
                    'value' => $difficulty->value,
                    'label' => $difficulty->label(),
                ])
                ->values(),
            'dietTypes' => collect(DietType::cases())
                ->map(fn (DietType $dietType): array => [
                    'value' => $dietType->value,
                    'label' => $dietType->label(),
                ])
                ->values(),
            'meals' => collect(Meal::cases())
                ->map(fn (Meal $meal): array => [
                    'value' => $meal->value,
                    'label' => $meal->label(),
                ])
                ->values(),
        ]);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $recipe->update($request->validated());

        return redirect(route('recipes.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Recipe updated successfully.',
            ]);
    }

    public function destroy(DestroyRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return redirect(route('recipes.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Recipe deleted successfully.',
            ]);
    }

    public function createRequest(Request $request): RedirectResponse
    {
        $recipeData = $this->validateRecipe($request);
        $ingredientData = $this->validateIngredient($request);

        $recipeData['user_id'] = $request->user()->id;
        $recipeData['status'] = RecipeStatus::Draft->value;

        $recipe = insert(Recipe::class, $recipeData);

        foreach ($ingredientData as $ingredient) {
            $ingredient['recipe_id'] = $recipe->id;
            insert(RecipeIngredient::class, $ingredient);
        }

        insert(RecipeRequest::class, [
            'recipe_id' => $recipe->id,
            'user_id' => $request->user()->id,
            'date' => now(),
        ]);

        return redirect(route('recipes.index', absolute: false))->with('toast', [
            'type' => 'success',
            'message' => 'Recipe request created successfully.',
        ]);
    }

    private function validateRecipe(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'preparation_time' => ['required', 'string', 'max:255'],
            'servings' => ['required', 'integer', 'min:1', 'max:255'],
            'difficulty' => ['required', 'string'],
            'calorie_intake' => ['required', 'integer', 'min:0'],
            'diet_type' => ['required', 'string'],
            'meal' => ['required', 'string'],
        ]);
    }

    private function validateIngredient(Request $request): array
    {
        if (! $request->filled('ingredients')) {
            return [];
        }

        return $request->validate([
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'ingredients.*.quantity' => ['required', 'integer', 'min:1'],
            'ingredients.*.measurement' => ['required', 'string', 'max:255'],
        ])['ingredients'];
    }
}
