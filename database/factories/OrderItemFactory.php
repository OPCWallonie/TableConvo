<?php

namespace Database\Factories;

use App\Models\CardType;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPriceHt = 206.61;
        $vatRate = 21.00;
        $qty = 1;

        return [
            'order_id' => Order::factory(),
            'card_type_id' => CardType::factory(),
            'quantity' => $qty,
            'unit_price_ht' => $unitPriceHt,
            'vat_rate' => $vatRate,
            'vat_amount' => round($unitPriceHt * $qty * ($vatRate / 100), 2, PHP_ROUND_HALF_UP),
            'total_ht' => round($unitPriceHt * $qty, 2, PHP_ROUND_HALF_UP),
            'total_ttc' => round($unitPriceHt * $qty * (1 + $vatRate / 100), 2, PHP_ROUND_HALF_UP),
        ];
    }
}
