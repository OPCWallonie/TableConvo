<?php

use App\Enums\OrderStatus;
use App\Filament\Widgets\RevenueChartWidget;
use App\Models\Order;

function makeRevenueWidget(): RevenueChartWidget
{
    return new class extends RevenueChartWidget {
        public function getDataPublic(): array
        {
            return $this->getData();
        }
    };
}

it('aggregates Paid orders by month for current month', function () {
    Order::factory()->count(2)->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
        'total_ht' => 206.61,
    ]);

    $data = makeRevenueWidget()->getDataPublic();

    expect($data['datasets'][0]['data'][11])->toBe(413.22);
});

it('excludes Pending and Failed orders from revenue', function () {
    Order::factory()->create([
        'status'   => OrderStatus::Pending,
        'paid_at'  => null,
        'total_ht' => 500.00,
    ]);
    Order::factory()->create([
        'status'   => OrderStatus::Failed,
        'paid_at'  => now(),
        'total_ht' => 500.00,
    ]);
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
        'total_ht' => 206.61,
    ]);

    $data = makeRevenueWidget()->getDataPublic();

    expect($data['datasets'][0]['data'][11])->toBe(206.61);
});

it('respects 12-month window (13 months ago not included)', function () {
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonths(13),
        'total_ht' => 999.99,
    ]);
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonths(11),
        'total_ht' => 206.61,
    ]);

    $data = makeRevenueWidget()->getDataPublic();

    expect($data['datasets'][0]['data'])->toHaveCount(12)
        ->and($data['datasets'][0]['data'][0])->toBe(206.61);
});
