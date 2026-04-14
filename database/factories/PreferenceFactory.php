<?php

namespace Database\Factories;

use App\Enums\PreferenceStatus;
use App\Models\Preference;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preference>
 */
class PreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipe_id' => Recipe::factory(),
            'status' => fake()->randomElement(PreferenceStatus::cases()),
        ];
    }
}
