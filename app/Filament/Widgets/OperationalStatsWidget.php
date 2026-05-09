<?php

namespace App\Filament\Widgets;

use App\Enums\CardStatus;
use App\Enums\OrderStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Order;
use App\Models\Registration;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getStats(): array
    {
        $upcomingSessions = ConversationTable::where('status', SessionStatus::Scheduled)
            ->whereBetween('scheduled_at', [now(), now()->endOfWeek()])
            ->count();

        $activeRegistrations = Registration::where('status', RegistrationStatus::Registered)
            ->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()))
            ->count();

        $activeCards = Card::where('status', CardStatus::Active)->count();

        $monthlyRevenue = Order::where('status', OrderStatus::Paid)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total_ht');

        return [
            Stat::make('Sessions cette semaine', $upcomingSessions)
                ->description('Sessions planifiées à venir cette semaine')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('primary'),

            Stat::make('Inscriptions en cours', $activeRegistrations)
                ->description('Inscrits confirmés, sessions à venir')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('success'),

            Stat::make('Cartes actives', $activeCards)
                ->description('Cartes avec des sessions disponibles')
                ->icon(Heroicon::OutlinedCreditCard)
                ->color('info'),

            Stat::make('Revenus du mois (HT)', number_format((float) $monthlyRevenue, 2, ',', ' ') . ' €')
                ->description('Commandes payées ce mois-ci')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning'),
        ];
    }
}
