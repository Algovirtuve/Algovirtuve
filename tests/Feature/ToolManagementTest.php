<?php

use App\Enums\ToolType;
use App\Models\Tool;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view the tool management page', function () {
    $user = User::factory()->createOne();
    $tool = Tool::create(['type' => ToolType::BAKING->value]);
    $user->tools()->attach($tool);

    $response = $this->actingAs($user)->get(route('tools.index'));

    $response->assertOk();
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Recipe_generation/tool_page')
            ->where('tools.0.type_label', 'Baking')
    );
});

test('authenticated users can view the tool creation page', function () {
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->get(route('tools.create'));

    $response->assertOk();
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Recipe_generation/tool_creation_page')
            ->has('tool_types')
    );
});

test('authenticated users can add a tool', function () {
    $user = User::factory()->createOne();

    $response = $this->actingAs($user)->post(route('tools.store'), [
        'type' => ToolType::CUTTING->value,
    ]);

    $response->assertRedirect(route('tools.index', absolute: false));
    $response->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Tool saved successfully.',
    ]);

    $this->assertDatabaseHas('tools', [
        'type' => ToolType::CUTTING->value,
    ]);
    $this->assertDatabaseHas('user_tool', [
        'user_id' => $user->id,
    ]);
});

test('authenticated users can remove their own tool', function () {
    $user = User::factory()->createOne();
    $tool = Tool::create(['type' => ToolType::COOKING->value]);
    $user->tools()->attach($tool);

    $response = $this->actingAs($user)->delete(route('tools.destroy', $tool));

    $response->assertRedirect(route('tools.index', absolute: false));
    $response->assertSessionHas('toast', [
        'type' => 'success',
        'message' => 'Tool removed successfully.',
    ]);

    $this->assertDatabaseMissing('user_tool', [
        'user_id' => $user->id,
        'tool_id' => $tool->id,
    ]);
    $this->assertDatabaseMissing('tools', [
        'id' => $tool->id,
    ]);
});
