<?php

use App\Enums\CardStatus;
use App\Enums\OrderStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Widgets\OperationalStatsWidget;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function makeOperationalWidget(): OperationalStatsWidget
{
    return new class extends OperationalStatsWidget {
        public function getStatsPublic(): array
        {
            return $this->getStats();
        }
    };
}

it('displays correct count of upcoming sessions this week', function () {
    ConversationTable::factory()->create([
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addHours(4),
    ]);
    ConversationTable::factory()->create([
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addWeeks(2),
    ]);

    $stats = makeOperationalWidget()->getStatsPublic();

    expect($stats[0]->getValue())->toBe(1);
});

it('displays correct count of active registrations', function () {
    $future = ConversationTable::factory()->create(['scheduled_at' => now()->addDays(3)]);
    Registration::factory()->create([
        'conversation_table_id' => $future->id,
        'status'                => RegistrationStatus::Registered,
    ]);
    Registration::factory()->create([
        'conversation_table_id' => $future->id,
        'status'                => RegistrationStatus::Registered,
    ]);
    $past = ConversationTable::factory()->create(['scheduled_at' => now()->subDays(3)]);
    Registration::factory()->create([
        'conversation_table_id' => $past->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $stats = makeOperationalWidget()->getStatsPublic();

    expect($stats[1]->getValue())->toBe(2);
});

it('displays correct count of active cards', function () {
    Card::factory()->count(3)->create(['status' => CardStatus::Active]);
    Card::factory()->count(2)->create(['status' => CardStatus::Expired]);

    $stats = makeOperationalWidget()->getStatsPublic();

    expect($stats[2]->getValue())->toBe(3);
});

it('displays correct revenue for current month (only Paid orders counted)', function () {
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
        'total_ht' => 206.61,
    ]);
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
        'total_ht' => 206.61,
    ]);

    $stats = makeOperationalWidget()->getStatsPublic();

    expect($stats[3]->getValue())->toBe('413,22 €');
});

it('excludes orders from previous months', function () {
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonths(2),
        'total_ht' => 500.00,
    ]);
    Order::factory()->create([
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
        'total_ht' => 206.61,
    ]);

    $stats = makeOperationalWidget()->getStatsPublic();

    expect($stats[3]->getValue())->toBe('206,61 €');
});

it('non-admin users cannot view the widget', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(OperationalStatsWidget::canView())->toBeFalse();
});
