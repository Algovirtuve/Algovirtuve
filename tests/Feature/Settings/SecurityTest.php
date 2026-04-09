<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings redirects to appearance settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/appearance');
});

test('appearance settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/appearance'),
        );
});
