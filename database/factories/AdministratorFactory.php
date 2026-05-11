<?php

namespace Database\Factories;

use App\Models\Administrator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Administrator>
 */
class AdministratorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Administrator>
     */
    protected $model = Administrator::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }
}
