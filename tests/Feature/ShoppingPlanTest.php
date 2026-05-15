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

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('products_plan_page')
        ->has('generated_plan')
        ->where('generated_plan.generation_date', now()->toDateString())
        ->has('generated_plan.cheapest_products')
    );

    $shoppingPlan = ShoppingPlan::query()->where('user_id', $user->id)->latest('id')->first();

    expect($shoppingPlan)->not->toBeNull();

    expect(StoreProduct::query()->where('shopping_plan_id', $shoppingPlan->id)->count())
        ->toBeGreaterThan(0);

    // Search step for adding a product (returns shops list)
    $response = $this->actingAs($user)->post(route('shopping_plan.searchForProducts', $shoppingPlan), [
        'product_title' => 'Milk',
    ]);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('products_plan_page')
        ->where('add_product.product_title', 'Milk')
        ->has('add_product.shops')
    );

    // Select-shop step for adding a product
    $response = $this->actingAs($user)->post(route('shopping_plan.insertNewProduct', $shoppingPlan), [
        'product_title' => 'Milk',
        'store_title' => 'Maxima',
        'address' => 'Fake g. 1',
        'city' => 'Vilnius',
        'price' => 1.23,
        'quantity' => 1,
        'measurement' => Measurement::UNIT->value,
        'generated_plan' => [
            'generation_date' => now()->toDateString(),
            'cheapest_products' => [],
        ],
    ]);

    $response->assertOk();

    $storeProduct = StoreProduct::query()
        ->where('shopping_plan_id', $shoppingPlan->id)
        ->whereHas('product', fn ($q) => $q->where('title', 'Milk'))
        ->latest('id')
        ->first();

    expect($storeProduct)->not->toBeNull();

    // Remove product
    $removeResponse = $this->actingAs($user)->delete(route('shopping_plan.removeProduct', [
        'shoppingPlan' => $shoppingPlan,
        'storeProduct' => $storeProduct,
    ]));

    $removeResponse->assertOk();

    $this->assertDatabaseMissing('store_product', [
        'id' => $storeProduct->id,
    ]);
});
