<?php

namespace Database\Factories;

use App\Enums\DietType;
use App\Enums\Meal;
use App\Enums\RecipeDifficulty;
use App\Enums\RecipeStatus;
use App\Models\Recipe;
use App\Models\User;
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
            'user_id' => User::factory(),
            'title' => fake()->unique()->sentence(3),
            'instructions' => fake()->paragraphs(3, true),
            'preparation_time' => fake()->numberBetween(10, 120).' minutes',
            'servings' => fake()->numberBetween(1, 8),
            'difficulty' => fake()->randomElement(RecipeDifficulty::cases()),
            'calorie_intake' => fake()->numberBetween(250, 950),
            'status' => fake()->randomElement(RecipeStatus::cases()),
            'diet_type' => fake()->randomElement(DietType::cases()),
            'meal' => fake()->randomElement(Meal::cases()),
        ];
    }
}
