<?php

use App\Actions\Registration\MoveRegistrationAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMoveSetup(): array
{
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $user  = User::factory()->withLevel($level)->create();

    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addMonths(6),
    ]);

    $tableA = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(7),
        'status'       => SessionStatus::Scheduled,
    ]);

    $tableB = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(14),
        'status'       => SessionStatus::Scheduled,
    ]);

    $registration = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $tableA->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subHour(),
    ]);

    return compact('admin', 'user', 'tableA', 'tableB', 'registration', 'card');
}

it('moves a registration to a new table', function () {
    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $result = app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin);

    expect($result->conversation_table_id)->toBe($tableB->id);
    expect($result->status)->toBe(RegistrationStatus::Registered);
});

it('throws when trying to move a cancelled registration', function () {
    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin))
        ->toThrow(RuntimeException::class, 'cannot_move_cancelled_registration');
});

it('can move a waitlist registration to a new table', function () {
    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    $result = app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin);

    expect($result->conversation_table_id)->toBe($tableB->id);
});
