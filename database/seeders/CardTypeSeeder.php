<?php

namespace Database\Seeders;

use App\Models\CardType;
use Illuminate\Database\Seeder;

class CardTypeSeeder extends Seeder
{
    public function run(): void
    {
        CardType::firstOrCreate(
            ['name' => 'Carte 10 sessions'],
            [
                'sessions_count' => 10,
                'price' => 250.00,
                'validity_months' => 12,
                'is_active' => true,
            ]
        );
    }
}
