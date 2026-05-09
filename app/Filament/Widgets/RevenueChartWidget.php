<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenus HT (12 mois)';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        return Cache::remember('widget.revenue', 300, function () {
            $labels = [];
            $data   = [];

            for ($i = 11; $i >= 0; $i--) {
                $date     = now()->subMonths($i);
                $labels[] = $date->format('M y');

                $data[] = (float) Order::where('status', OrderStatus::Paid)
                    ->whereMonth('paid_at', $date->month)
                    ->whereYear('paid_at', $date->year)
                    ->sum('total_ht');
            }

            return [
                'datasets' => [
                    [
                        'label'       => 'Revenus HT (€)',
                        'data'        => $data,
                        'borderColor' => 'rgba(16, 185, 129, 1)',
                        'fill'        => false,
                        'tension'     => 0.3,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
}
