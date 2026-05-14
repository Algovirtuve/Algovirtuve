<?php

use App\Enums\IngredientCategory;
use App\Models\Ingredient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view the ingredient management page', function () {
    $user = User::factory()->createOne();
    $ingredient = Ingredient::create(['category' => IngredientCategory::VEGETABLE->value]);
    $user->ingredients()->attach($ingredient, [
        'quantity' => 1,
        'expiry_date' => now()->addDays(7)->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('ingredients.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Recipe_generation/ingredient_page')
            ->where('ingredients.0.category_label', 'Vegetable')
    );
});

test('authenticated users can view the ingredient creation page', function () {
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->get(route('ingredients.create'));

    $response->assertOk();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Recipe_generation/ingredient_creation_page')
            ->has('ingredient_categories')
    );
});

test('authenticated users can add an ingredient', function () {
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->post(route('ingredients.store'), [
        'category' => IngredientCategory::MEAT->value,
    ]);

    $response->assertRedirect(route('ingredients.index', absolute: false));
    $response->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Ingredient saved successfully.',
    ]);

    $this->assertDatabaseHas('ingredients', [
        'category' => IngredientCategory::MEAT->value,
    ]);
    $this->assertDatabaseHas('user_ingredient', [
        'user_id' => $user->id,
        'quantity' => 1,
    ]);
});

test('authenticated users can remove their own ingredient', function () {
    $user = User::factory()->createOne();
    $ingredient = Ingredient::create(['category' => IngredientCategory::DAIRY->value]);
    $user->ingredients()->attach($ingredient, [
        'quantity' => 1,
        'expiry_date' => now()->addDays(7)->toDateString(),
    ]);

    $response = $this->actingAs($user)->delete(route('ingredients.destroy', $ingredient));

    $response->assertRedirect(route('ingredients.index', absolute: false));
    $response->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Ingredient removed successfully.',
    ]);

    $this->assertDatabaseMissing('user_ingredient', [
        'user_id' => $user->id,
        'ingredient_id' => $ingredient->id,
    ]);
    $this->assertDatabaseMissing('ingredients', [
        'id' => $ingredient->id,
    ]);
});
