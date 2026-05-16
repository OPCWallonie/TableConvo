<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Resources\ConversationTables\ConversationTableResource;
use App\Models\ConversationTable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class SessionFillRateChartWidget extends ChartWidget
{
    protected ?string $heading = 'Taux de remplissage (12 semaines)';

    protected string $view = 'filament.widgets.session-fill-rate-chart-with-link';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getDrillDownUrl(): string
    {
        return ConversationTableResource::getUrl('index') . '?' . http_build_query([
            'tableFilters' => [
                'status' => ['value' => 'completed'],
                'period' => ['value' => 'past'],
            ],
        ]);
    }

    protected function getData(): array
    {
        return Cache::remember('widget.fill_rate', 300, function () {
            $labels = [];
            $data   = [];

            for ($i = 11; $i >= 0; $i--) {
                $weekStart = now()->startOfWeek()->subWeeks($i);
                $weekEnd   = $weekStart->copy()->endOfWeek();

                $labels[] = 'S' . $weekStart->week;

                $sessions = ConversationTable::where('status', SessionStatus::Completed)
                    ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
                    ->withCount([
                        'registrations as attended_count' => fn ($q) => $q->where('status', RegistrationStatus::Attended),
                    ])
                    ->get();

                if ($sessions->isEmpty()) {
                    $data[] = 0;
                } else {
                    $totalFillRate = $sessions->sum(
                        fn ($s) => $s->max_participants > 0
                            ? ($s->attended_count / $s->max_participants) * 100
                            : 0
                    );
                    $data[] = round($totalFillRate / $sessions->count(), 1);
                }
            }

            return [
                'datasets' => [
                    [
                        'label'           => 'Taux de remplissage (%)',
                        'data'            => $data,
                        'backgroundColor' => 'rgba(99, 102, 241, 0.5)',
                        'borderColor'     => 'rgba(99, 102, 241, 1)',
                        'borderWidth'     => 1,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }
}
