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
            'title' => $this->faker->unique()->sentence(3),
            'instructions' => $this->faker->paragraphs(3, true),
            'preparation_time' => $this->faker->numberBetween(10, 120).' minutes',
            'servings' => $this->faker->numberBetween(1, 8),
            'difficulty' => $this->faker->randomElement(RecipeDifficulty::cases()),
            'calorie_intake' => $this->faker->numberBetween(250, 950),
            'status' => $this->faker->randomElement(RecipeStatus::cases()),
            'diet_type' => $this->faker->randomElement(DietType::cases()),
            'meal' => $this->faker->randomElement(Meal::cases()),
        ];
    }
}
