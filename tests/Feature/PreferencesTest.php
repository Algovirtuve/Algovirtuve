<?php

use App\Enums\PreferenceStatus;
use App\Enums\RecipeStatus;
use App\Models\Preference;
use App\Models\Recipe;
use App\Models\User;

test('authenticated users can view their preferences page', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    Preference::factory()->for($user)->for($recipe)->create([
        'status' => PreferenceStatus::Liked,
    ]);

    $response = $this->actingAs($user)->get(route('preferences.index'));

    $response->assertOk();
    $response->assertSee('Recipe preferences');
    $response->assertSee($recipe->title);
});

test('authenticated users can create a preference', function () {
    /** @var User $user */
    $user = User::factory()->createOne();
    $recipe = Recipe::factory()->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    $response = $this->actingAs($user)->post(route('preferences.store'), [
        'recipe_id' => $recipe->id,
        'status' => PreferenceStatus::Liked->value,
    ]);

    $response->assertRedirect(route('preferences.index'));

    $this->assertDatabaseHas('preferences', [
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'status' => PreferenceStatus::Liked->value,
    ]);
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
