<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings redirects to appearance settings', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/appearance');
});

test('appearance settings page is displayed', function () {
    /** @var User $user */
    $user = User::factory()->createOne();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Personalization/settings_page'),
        );
});
