<?php

use App\Enums\RecipeStatus;
use App\Models\Administrator;
use App\Models\Recipe;
use App\Models\Request as AdminRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('non administrators cannot access admin pages', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->get(route('admin.index'));

    $response->assertForbidden();
});

test('administrators can view the admin dashboard with counts', function () {
    /** @var User $adminUser */
    $adminUser = User::factory()->createOne();
    Administrator::factory()->createOne([
        'user_id' => $adminUser->id,
    ]);

    /** @var User $requester */
    $requester = User::factory()->createOne();

    $draftRecipe = Recipe::factory()->for($requester, 'owner')->createOne([
        'status' => RecipeStatus::Draft,
    ]);
    $acceptedRecipe = Recipe::factory()->for($requester, 'owner')->createOne([
        'status' => RecipeStatus::Accepted,
    ]);
    $declinedRecipe = Recipe::factory()->for($requester, 'owner')->createOne([
        'status' => RecipeStatus::Declined,
    ]);

    AdminRequest::factory()->createOne([
        'user_id' => $requester->id,
        'recipe_id' => $draftRecipe->id,
    ]);
    AdminRequest::factory()->createOne([
        'user_id' => $requester->id,
        'recipe_id' => $acceptedRecipe->id,
    ]);
    AdminRequest::factory()->createOne([
        'user_id' => $requester->id,
        'recipe_id' => $declinedRecipe->id,
    ]);

    $response = $this->actingAs($adminUser)->get(route('admin.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Administration/admin_page')
        ->where('requests_count', AdminRequest::count())
        ->where('waiting_requests_count', 1)
        ->where('approved_requests_count', 1)
        ->where('rejected_requests_count', 1)
        ->where('users_count', User::count())
    );
});

test('administrators can browse requests and approve a waiting request', function () {
    /** @var User $adminUser */
    $adminUser = User::factory()->createOne();
    $administrator = Administrator::factory()->createOne([
        'user_id' => $adminUser->id,
    ]);

    /** @var User $requester */
    $requester = User::factory()->createOne();

    $draftRecipe = Recipe::factory()->for($requester, 'owner')->createOne([
        'status' => RecipeStatus::Draft,
    ]);

    /** @var AdminRequest $requestModel */
    $requestModel = AdminRequest::factory()->createOne([
        'user_id' => $requester->id,
        'recipe_id' => $draftRecipe->id,
    ]);

    $indexResponse = $this->actingAs($adminUser)->get(route('admin.requests.index'));

    $indexResponse->assertOk();
    $indexResponse->assertInertia(fn (Assert $page) => $page
        ->component('Administration/requests_page')
        ->where('requests.0.id', $requestModel->id)
        ->where('requests.0.recipe.status', RecipeStatus::Draft->value)
    );

    $showResponse = $this->actingAs($adminUser)->get(route('admin.requests.show', $requestModel));

    $showResponse->assertOk();
    $showResponse->assertInertia(fn (Assert $page) => $page
        ->component('Administration/request_page')
        ->where('request.id', $requestModel->id)
        ->where('request.recipe.status', RecipeStatus::Draft->value)
    );

    $approveResponse = $this->actingAs($adminUser)->patch(route('admin.requests.approve', $requestModel));

    $approveResponse->assertRedirect(route('admin.requests.show', $requestModel, absolute: false));
    $approveResponse->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Request approved successfully.',
    ]);

    expect($draftRecipe->fresh()->status)->toBe(RecipeStatus::Accepted);
    $this->assertDatabaseHas('requests', [
        'id' => $requestModel->id,
        'administrator_id' => $administrator->id,
    ]);
});

test('administrators cannot approve an already reviewed request', function () {
    /** @var User $adminUser */
    $adminUser = User::factory()->createOne();
    Administrator::factory()->createOne([
        'user_id' => $adminUser->id,
    ]);

    /** @var User $requester */
    $requester = User::factory()->createOne();

    $acceptedRecipe = Recipe::factory()->for($requester, 'owner')->createOne([
        'status' => RecipeStatus::Accepted,
    ]);

    /** @var AdminRequest $requestModel */
    $requestModel = AdminRequest::factory()->createOne([
        'user_id' => $requester->id,
        'recipe_id' => $acceptedRecipe->id,
    ]);

    $response = $this->actingAs($adminUser)->patch(route('admin.requests.approve', $requestModel));

    $response->assertForbidden();
});
