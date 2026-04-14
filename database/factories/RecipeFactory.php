<?php

namespace Database\Factories;

use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'instructions' => fake()->paragraphs(3, true),
            'preparation_time_minutes' => fake()->numberBetween(10, 120),
            'servings' => fake()->numberBetween(1, 8),
            'difficulty' => fake()->randomElement(RecipeDifficulty::cases()),
            'calorie_count' => fake()->numberBetween(250, 950),
            'status' => fake()->randomElement(RecipeStatus::cases()),
        ];
    }
}
