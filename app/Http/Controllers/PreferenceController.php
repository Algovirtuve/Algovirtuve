<?php

namespace App\Http\Controllers;

use App\Enums\PreferenceStatus;
use App\Enums\RecipeStatus;
use App\Http\Requests\DestroyPreferenceRequest;
use App\Http\Requests\StorePreferenceRequest;
use App\Models\Preference;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function preferences(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('preferences/index', [
            'preferences' => $user->preferences()
                ->with('recipe')
                ->latest('generation_date')
                ->get()
                ->map(fn (Preference $preference): array => [
                    'id' => $preference->id,
                    'preference_status' => $preference->preference_status->value,
                    'status_label' => $preference->preference_status->label(),
                    'generation_date' => $preference->generation_date?->toDateString(),
                    'recipe' => [
                        'id' => $preference->recipe->id,
                        'title' => $preference->recipe->title,
                        'instructions' => $preference->recipe->instructions,
                        'preparation_time' => $preference->recipe->preparation_time,
                        'servings' => $preference->recipe->servings,
                        'difficulty' => $preference->recipe->difficulty->value,
                        'difficulty_label' => $preference->recipe->difficulty->label(),
                        'calorie_intake' => $preference->recipe->calorie_intake,
                        'status' => $preference->recipe->status->value,
                        'status_label' => $preference->recipe->status->label(),
                        'diet_type' => $preference->recipe->diet_type->value,
                        'diet_type_label' => $preference->recipe->diet_type->label(),
                        'meal' => $preference->recipe->meal->value,
                        'meal_label' => $preference->recipe->meal->label(),
                    ],
                ]),
        ]);
    }

    public function createPreference(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('preferences/create', [
            'recipes' => Recipe::query()
                ->whereNotIn('id', $user->preferences()->select('recipe_id'))
                ->where('status', RecipeStatus::Accepted->value)
                ->orderBy('title')
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

        $preference->delete();

        return redirect(route('preferences.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Preference removed successfully.',
            ]);
    }
}
