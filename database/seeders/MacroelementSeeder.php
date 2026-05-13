<?php

namespace Database\Seeders;

use App\Enums\Measurement;
use App\Models\Macroelement;
use Illuminate\Database\Seeder;

class MacroelementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $macroelements = [
            ['title' => 'Protein', 'measurement' => Measurement::G],
            ['title' => 'Carbohydrates', 'measurement' => Measurement::G],
            ['title' => 'Fat', 'measurement' => Measurement::G],
        ];

        foreach ($macroelements as $macro) {
            Macroelement::firstOrCreate([
                'title' => $macro['title'],
            ], [
                'measurement' => $macro['measurement'],
            ]);
        }
    }
}
