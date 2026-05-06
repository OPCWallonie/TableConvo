<?php

use App\Actions\Registration\PromoteFromWaitlistAction;
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

function makeWaitlistSetup(int $maxParticipants = 1, int $sessionsRemaining = 5): array
{
    $level = Level::factory()->create();
    $admin = User::factory()->create();

    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'max_participants' => $maxParticipants,
        'scheduled_at'     => now()->addDays(7),
        'status'           => SessionStatus::Scheduled,
    ]);

    $user = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'sessions_remaining' => $sessionsRemaining,
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);

    $registration = Registration::create([
        'user_id'                => $user->id,
        'conversation_table_id'  => $table->id,
        'card_id'                => null,
        'status'                 => RegistrationStatus::Waitlist,
        'waitlist_position'      => 1,
        'registered_at'          => now()->subHour(),
    ]);

    return compact('admin', 'user', 'table', 'card', 'registration');
}

it('promotes a waitlist registration to registered and decrements card', function () {
    ['admin' => $admin, 'card' => $card, 'registration' => $registration] = makeWaitlistSetup();

    $sessionsBefore = $card->sessions_remaining;

    $result = app(PromoteFromWaitlistAction::class)->execute($registration, $admin);

    expect($result->status)->toBe(RegistrationStatus::Registered);
    expect($result->waitlist_position)->toBeNull();
    expect($result->card_id)->toBe($card->id);
    expect($card->fresh()->sessions_remaining)->toBe($sessionsBefore - 1);
});

it('throws when registration is not on waitlist', function () {
    ['admin' => $admin, 'registration' => $registration] = makeWaitlistSetup();

    $registration->update(['status' => RegistrationStatus::Registered]);

    expect(fn () => app(PromoteFromWaitlistAction::class)->execute($registration, $admin))
        ->toThrow(RuntimeException::class, 'registration_not_on_waitlist');
});

it('throws when table is still full', function () {
    $setup = makeWaitlistSetup(maxParticipants: 1);
    $admin = $setup['admin'];
    $table = $setup['table'];
    $registration = $setup['registration'];

    // Fill the table with another confirmed registration
    $fillerUser = User::factory()->withLevel($table->level)->create();
    $fillerOrder = Order::factory()->create(['user_id' => $fillerUser->id]);
    $fillerCard = Card::factory()->create([
        'user_id'  => $fillerUser->id,
        'order_id' => $fillerOrder->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addMonths(3),
    ]);
    Registration::create([
        'user_id'               => $fillerUser->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $fillerCard->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subMinutes(30),
    ]);

    expect(fn () => app(PromoteFromWaitlistAction::class)->execute($registration, $admin))
        ->toThrow(RuntimeException::class, 'table_still_full');
});

it('throws when user has no active card for promotion', function () {
    ['admin' => $admin, 'user' => $user, 'card' => $card, 'registration' => $registration] = makeWaitlistSetup();

    // Expire the card so user has no active card
    $card->update(['status' => CardStatus::Expired, 'expires_at' => now()->subDay()]);

    expect(fn () => app(PromoteFromWaitlistAction::class)->execute($registration, $admin))
        ->toThrow(RuntimeException::class, 'no_active_card_for_promotion');
});
