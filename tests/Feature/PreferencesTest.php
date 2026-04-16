<?php

use App\Enums\PreferenceStatus;
use App\Enums\RecipeStatus;
use App\Models\Preference;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view their preferences page', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    Preference::factory()->for($user)->for($recipe)->create([
        'preference_status' => PreferenceStatus::Liked,
    ]);

    $response = $this->actingAs($user)->get(route('preferences.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('preferences/index')
        ->where('preferences.0.recipe.title', $recipe->title)
        ->where('preferences.0.preference_status', PreferenceStatus::Liked->value)
        ->where('preferences.0.status_label', PreferenceStatus::Liked->label())
        ->where('preferences.0.recipe.difficulty_label', $recipe->difficulty->label())
        ->where('preferences.0.recipe.status_label', $recipe->status->label())
        ->where('preferences.0.recipe.diet_type_label', $recipe->diet_type->label())
        ->where('preferences.0.recipe.meal_label', $recipe->meal->label())
    );
});

test('authenticated users can create a preference', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    $response = $this->actingAs($user)->post(route('preferences.store'), [
        'recipe_id' => $recipe->id,
        'preference_status' => PreferenceStatus::Liked->value,
    ]);

    $response->assertRedirect(route('preferences.index'));

    $this->assertDatabaseHas('preferences', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'preference_status' => PreferenceStatus::Liked->value,
    ]);
});

test('users cannot create preferences for recipes that are not accepted', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Draft,
    ]);

    $response = $this->actingAs($user)
        ->from(route('preferences.create'))
        ->post(route('preferences.store'), [
            'recipe_id' => $recipe->id,
            'preference_status' => PreferenceStatus::Liked->value,
        ]);

    $response->assertRedirect(route('preferences.create', absolute: false));
    $response->assertSessionHasErrors('recipe_id');
    $this->assertDatabaseMissing('preferences', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
    ]);
});

test('favorite recipes only include liked preferences', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $likedRecipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);
    $dislikedRecipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    Preference::factory()->for($user)->for($likedRecipe)->createOne([
        'preference_status' => PreferenceStatus::Liked,
    ]);

    Preference::factory()->for($user)->for($dislikedRecipe)->createOne([
        'preference_status' => PreferenceStatus::Disliked,
    ]);

    expect($user->preferredRecipes()->pluck('recipes.id')->all())
        ->toEqualCanonicalizing([$likedRecipe->id, $dislikedRecipe->id]);

    expect($user->favoriteRecipes()->pluck('recipes.id')->all())
        ->toEqual([$likedRecipe->id]);
});

test('authenticated users can delete their own preference', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $preference = Preference::factory()->for($user)->createOne();

    $response = $this->actingAs($user)->delete(route('preferences.destroy', $preference));

    $response->assertRedirect(route('preferences.index'));
    $this->assertDatabaseMissing('preferences', [
        'id' => $preference->id,
    ]);
});

test('users cannot delete another users preference', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $preference = Preference::factory()->createOne();

    $response = $this->actingAs($user)->delete(route('preferences.destroy', $preference));

    $response->assertNotFound();
    $this->assertDatabaseHas('preferences', [
        'id' => $preference->id,
    ]);
});
