<?php

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\Measurement;
use App\Models\DietPlan;
use App\Models\Macroelement;
use App\Models\Recipe;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view the diet page', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    Macroelement::query()->create([
        'title' => 'Protein',
        'measurement' => Measurement::G,
    ]);

    $response = $this->actingAs($user)->get(route('diet.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Health_managment/diet_page')
        ->has('macroelements')
        ->has('dietTypes')
        ->has('meals')
    );
});

test('authenticated users can view the diet plan generation page with temp state', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $macro = Macroelement::query()->create([
        'title' => 'Protein',
        'measurement' => Measurement::G,
    ]);

    $response = $this->actingAs($user)
        ->withSession([
            'diet_plan_generation.temp.macroelements' => [
                ['id' => $macro->id, 'target_kcal' => 200],
            ],
            'diet_plan_generation.temp.diet_type' => DietType::Vegetarian->value,
            'diet_plan_generation.temp.day_calorie_limit' => 1000,
        ])
        ->get(route('diet.generate.view'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Health_managment/diet_plan_generation_page')
        ->where('temp.diet_type', DietType::Vegetarian->value)
        ->where('temp.day_calorie_limit', 1000)
        ->where('temp.macroelements.0.id', $macro->id)
        ->where('temp.macroelements.0.target_kcal', 200)
    );
});

test('temp endpoints store wizard state in session', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $macro = Macroelement::query()->create([
        'title' => 'Protein',
        'measurement' => Measurement::G,
    ]);

    $response = $this->actingAs($user)->post(route('diet.temp.macros'), [
        'macroelements' => [
            ['id' => $macro->id, 'target_kcal' => 250],
        ],
    ]);

    $response->assertRedirect(route('diet.generate.view', absolute: false));
    $response->assertSessionHas('diet_plan_generation.temp.macroelements');

    $response = $this->actingAs($user)->post(route('diet.temp.type'), [
        'diet_type' => DietType::Vegan->value,
    ]);

    $response->assertRedirect(route('diet.generate.view', absolute: false));
    $response->assertSessionHas('diet_plan_generation.temp.diet_type', DietType::Vegan->value);

    $response = $this->actingAs($user)->post(route('diet.temp.calorie'), [
        'day_calorie_limit' => 1200,
    ]);

    $response->assertRedirect(route('diet.generate.view', absolute: false));
    $response->assertSessionHas('diet_plan_generation.temp.day_calorie_limit', 1200);
});

test('diet plan generation splits calories 20/50/30 and returns top 3 recipes per meal', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $macroA = Macroelement::query()->create([
        'title' => 'Protein',
        'measurement' => Measurement::G,
    ]);

    $macroB = Macroelement::query()->create([
        'title' => 'Carbs',
        'measurement' => Measurement::G,
    ]);

    // Day limit = 1000 => breakfast 200, lunch 500, dinner 300
    Recipe::factory()->createMany([
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Breakfast, 'calorie_intake' => 200],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Breakfast, 'calorie_intake' => 180],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Breakfast, 'calorie_intake' => 260],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Breakfast, 'calorie_intake' => 700],

        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Lunch, 'calorie_intake' => 500],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Lunch, 'calorie_intake' => 520],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Lunch, 'calorie_intake' => 440],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Lunch, 'calorie_intake' => 950],

        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Dinner, 'calorie_intake' => 300],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Dinner, 'calorie_intake' => 320],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Dinner, 'calorie_intake' => 250],
        ['diet_type' => DietType::Vegetarian, 'meal' => Meal::Dinner, 'calorie_intake' => 900],
    ]);

    $response = $this->actingAs($user)->post(route('diet.generate'), [
        'macroelements' => [
            ['id' => $macroA->id, 'target_kcal' => 300],
            ['id' => $macroB->id, 'target_kcal' => 150],
        ],
        'diet_type' => DietType::Vegetarian->value,
        'day_calorie_limit' => 1000,
    ]);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Health_managment/diet_plan_page')
        ->where('generatedPlan.day_calorie_limit', 1000)
        ->where('generatedPlan.meal_calorie_limits.breakfast', 200)
        ->where('generatedPlan.meal_calorie_limits.lunch', 500)
        ->where('generatedPlan.meal_calorie_limits.dinner', 300)
        ->has('recommendations.breakfast', 3)
        ->has('recommendations.lunch', 3)
        ->has('recommendations.dinner', 3)
    );

    $dietPlan = DietPlan::query()->latest('id')->firstOrFail();

    $this->assertDatabaseHas('diet_plans', [
        'id' => $dietPlan->id,
        'diet_type' => DietType::Vegetarian->value,
    ]);

    $this->assertDatabaseCount('diet_plan_macroelement', 2);
    $this->assertDatabaseHas('diet_plan_macroelement', [
        'diet_plan_id' => $dietPlan->id,
        'macroelement_id' => $macroA->id,
    ]);

    // Up to 9 recipes attached (3 meals x 3 recipes)
    expect($dietPlan->recipes()->count())->toBeGreaterThan(0);
});

test('authenticated users can view the last generated diet plan', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $dietPlan = DietPlan::query()->create(['diet_type' => DietType::Vegetarian->value]);

    $response = $this->actingAs($user)->get(route('diet.plan'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Health_managment/diet_plan_page')
        ->has('generatedPlan.id')
    );
});
