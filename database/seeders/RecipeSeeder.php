<?php

namespace Database\Seeders;

use App\Enums\RecipeStatus;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Recipe::factory()
            ->count(10)
            ->create([
                'status' => RecipeStatus::Accepted,
            ]);
    }
}
