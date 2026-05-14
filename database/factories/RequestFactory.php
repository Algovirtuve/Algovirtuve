<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
class RequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Request>
     */
    protected $model = Request::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'user_id' => User::factory(),
            'administrator_id' => null,
            'date' => $this->faker->date(),
        ];
    }
}
