<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'A1', 'name' => 'Débutant', 'description' => 'Niveau débutant - premiers pas en néerlandais', 'sort_order' => 1],
            ['code' => 'A2', 'name' => 'Élémentaire', 'description' => 'Niveau élémentaire - communication de base', 'sort_order' => 2],
            ['code' => 'B1', 'name' => 'Intermédiaire', 'description' => 'Niveau intermédiaire - conversations quotidiennes', 'sort_order' => 3],
            ['code' => 'B2', 'name' => 'Intermédiaire avancé', 'description' => 'Niveau intermédiaire avancé - aisance conversationnelle', 'sort_order' => 4],
            ['code' => 'C1', 'name' => 'Avancé', 'description' => 'Niveau avancé - maîtrise courante', 'sort_order' => 5],
            ['code' => 'C2', 'name' => 'Maîtrise', 'description' => 'Niveau maîtrise - quasi bilingue', 'sort_order' => 6],
        ];

        foreach ($levels as $level) {
            Level::firstOrCreate(['code' => $level['code']], array_merge($level, ['is_active' => true]));
        }
    }
}
