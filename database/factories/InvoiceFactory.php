<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $totalHt = 206.61;
        $totalVat = 43.39;

        return [
            'order_id' => Order::factory(),
            'invoice_number' => 'FAC-' . now()->year . '-' . str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'issued_at' => now(),
            'total_ht' => $totalHt,
            'total_vat' => $totalVat,
            'total_ttc' => $totalHt + $totalVat,
            'billing_snapshot' => [
                'recipient' => [
                    'name' => fake()->company(),
                    'vat_number' => 'BE0' . fake()->numerify('#########'),
                    'street' => fake()->streetAddress(),
                    'postal_code' => fake()->numerify('####'),
                    'city' => fake()->city(),
                    'country' => 'Belgique',
                ],
                'issuer' => [
                    'company_name' => 'TableConvo SRL',
                    'vat_number' => 'BE0123456789',
                    'rpm' => 'RPM Liège',
                    'legal_form' => 'SRL',
                    'street' => 'Rue de la Paix 1',
                    'postal_code' => '4000',
                    'city' => 'Liège',
                    'country' => 'Belgique',
                    'iban' => 'BE68539007547034',
                    'bic' => 'KREDBEBB',
                    'bank_name' => 'KBC',
                ],
            ],
        ];
    }
}
