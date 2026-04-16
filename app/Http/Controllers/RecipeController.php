<?php

namespace App\Http\Controllers;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Http\Requests\DestroyRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('recipes/index', [
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
}
