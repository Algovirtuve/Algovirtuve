<?php

namespace App\Http\Controllers\Recipe_generation;

use App\Enums\IngredientCategory;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\UserIngredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ingredients_controller extends Controller
{
    public function showIngredients(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Recipe_generation/ingredient_page', [
            'ingredients' => UserIngredient::with('ingredient')
                ->where('user_id', $user->id)
                ->get()
                ->map(static fn (UserIngredient $userIngredient): array => [
                    'id' => $userIngredient->ingredient->id,
                    'category' => $userIngredient->ingredient->category->value,
                    'category_label' => ucwords(str_replace('_', ' ', $userIngredient->ingredient->category->value)),
                ])
                ->all(),
        ]);
    }

    public function showIngredientCreationPage(Request $request): Response
    {
        return Inertia::render('Recipe_generation/ingredient_creation_page', [
            'ingredient_categories' => array_map(
                static fn (IngredientCategory $category): array => [
                    'value' => $category->value,
                    'label' => ucwords(str_replace('_', ' ', $category->value)),
                ],
                IngredientCategory::cases(),
            ),
        ]);
    }

    public function createIngredient(Request $request): RedirectResponse
    {
        $ingredientData = self::validateNewIngredientData($request);

        $ingredient = Ingredient::where('category', $ingredientData['category'])->first();

        if ($ingredient === null) {
            $ingredient = insert(Ingredient::class, $ingredientData);
        }

        if (UserIngredient::where('user_id', $request->user()->id)->where('ingredient_id', $ingredient->id)->doesntExist()) {
            insert(UserIngredient::class, ['user_id' => $request->user()->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1, 'expiry_date' => now()->addWeek()]);
        }

        return redirect()->route('ingredients.index')->with('toast', [
            'type' => 'success',
            'message' => 'Ingredient saved successfully.',
        ]);
    }

    public function deleteIngredient(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $user = $request->user();

        abort_if(! UserIngredient::where('user_id', $user->id)->where('ingredient_id', $ingredient->id)->exists(), 404);

        UserIngredient::where('user_id', $user->id)->where('ingredient_id', $ingredient->id)->delete();

        if (! $ingredient->users()->exists()) {
            $ingredient->delete();
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Ingredient removed successfully.',
        ]);
    }

    private static function validateNewIngredientData(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_column(IngredientCategory::cases(), 'value'))],
        ]);
    }
}
