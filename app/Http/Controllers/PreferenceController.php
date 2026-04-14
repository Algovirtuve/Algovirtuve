<?php

namespace App\Http\Controllers;

use App\Enums\PreferenceStatus;
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
                ->latest()
                ->get()
                ->map(fn (Preference $preference): array => [
                    'id' => $preference->id,
                    'status' => $preference->status->value,
                    'status_label' => $preference->status === PreferenceStatus::Liked ? 'Liked' : 'Disliked',
                    'created_at' => $preference->created_at?->toDateTimeString(),
                    'recipe' => [
                        'id' => $preference->recipe->id,
                        'title' => $preference->recipe->title,
                        'instructions' => $preference->recipe->instructions,
                        'preparation_time_minutes' => $preference->recipe->preparation_time_minutes,
                        'servings' => $preference->recipe->servings,
                        'difficulty' => $preference->recipe->difficulty->value,
                        'calorie_count' => $preference->recipe->calorie_count,
                        'status' => $preference->recipe->status->value,
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
                ->where('status', 'published')
                ->orderBy('title')
                ->get()
                ->map(fn (Recipe $recipe): array => [
                    'id' => $recipe->id,
                    'title' => $recipe->title,
                    'instructions' => $recipe->instructions,
                    'preparation_time_minutes' => $recipe->preparation_time_minutes,
                    'servings' => $recipe->servings,
                    'difficulty' => $recipe->difficulty->value,
                    'calorie_count' => $recipe->calorie_count,
                    'status' => $recipe->status->value,
                ]),
            'statuses' => collect(PreferenceStatus::cases())
                ->map(fn (PreferenceStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status === PreferenceStatus::Liked ? 'Liked' : 'Disliked',
                ])
                ->values(),
        ]);
    }

    public function storePreference(StorePreferenceRequest $request): RedirectResponse
    {
        $request->user()->preferences()->create($request->validated());

        return redirect(route('preferences.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Preference created successfully.',
            ]);
    }

    public function destroyPreference(Request $request, Preference $preference): RedirectResponse
    {
        abort_unless($preference->user_id === $request->user()?->getKey(), 404);

        $preference->delete();

        return redirect(route('preferences.index', absolute: false))
            ->with('toast', [
                'type' => 'success',
                'message' => 'Preference removed successfully.',
            ]);
    }
}
