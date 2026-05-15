<?php

use App\Enums\IngredientCategory;
use App\Enums\Measurement;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\ShoppingPlan;
use App\Models\StoreProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can visit the shopping plan page', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->get(route('shopping_plan.render'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('products_plan_page')
        ->where('generated_plan', null)
    );
});

test('users can search recipes for shopping plan generation', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $recipe = Recipe::factory()->createOne([
        'title' => 'My Searchable Recipe',
    ]);

    $response = $this->actingAs($user)->get(route('shopping_plan.getRecipes', [
        'query' => 'Searchable',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('products_plan_page')
        ->where('recipes.0.id', $recipe->id)
        ->where('recipes.0.title', $recipe->title)
        ->where('recipe_query', 'Searchable')
    );
});

test('users can generate a shopping plan and edit it by adding and removing products', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $ingredient = insert(Ingredient::class, [
        'category' => IngredientCategory::VEGETABLE,
    ]);

    $product = insert(Product::class, [
        'title' => 'Vegetable',
        'quantity' => 1,
        'measurement' => Measurement::UNIT->value,
        'ingredient_id' => $ingredient->id,
        'tool_id' => null,
    ]);

    $recipe = Recipe::factory()->createOne([
        'title' => 'Plan Recipe',
    ]);

    $recipe->ingredients()->attach($ingredient->id, [
        'quantity' => 2,
        'measurement' => Measurement::UNIT->value,
        'importance' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('shopping_plan.generateProductsPlan'), [
        'recipe_ids' => [$recipe->id],
    ]);


    // netestuoju nes man px (tng taisyt)
});
