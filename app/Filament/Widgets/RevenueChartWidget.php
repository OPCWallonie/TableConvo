<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenus HT (12 mois)';

    protected string $view = 'filament.widgets.revenue-chart-with-link';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getDrillDownUrl(): string
    {
        return OrderResource::getUrl('index') . '?' . http_build_query([
            'filters' => [
                'status'         => ['value' => 'paid'],
                'last_12_months' => ['isActive' => '1'],
            ],
        ]);
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
