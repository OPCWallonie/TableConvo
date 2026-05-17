<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NoShowRateWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getStats(): array
    {
        $noShowCount = Registration::where('status', RegistrationStatus::NoShow)
            ->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()->subDays(30)))
            ->count();

        $totalCount = Registration::whereIn('status', [
            RegistrationStatus::Registered,
            RegistrationStatus::NoShow,
            RegistrationStatus::Attended,
        ])
            ->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()->subDays(30)))
            ->count();

        $rate = $totalCount > 0 ? round(($noShowCount / $totalCount) * 100, 1) : 0.0;

        $color = match (true) {
            $rate < 10  => 'success',
            $rate <= 20 => 'warning',
            default     => 'danger',
        };

        return [
            Stat::make('Taux de no-show (30 j)', $rate . ' %')
                ->description($noShowCount . ' absent(s) sur ' . $totalCount . ' inscription(s)')
                ->icon(Heroicon::OutlinedUserMinus)
                ->color($color)
                ->url(RegistrationResource::getUrl('index') . '?' . http_build_query([
                    'filters' => [
                        'status'               => ['value' => 'no_show'],
                        'session_past_30_days' => ['isActive' => '1'],
                    ],
                ])),
        ];
    }
}
