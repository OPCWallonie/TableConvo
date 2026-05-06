<?php

use App\Actions\Registration\CancelRegistrationAction;
use App\Actions\Registration\RegisterUserToTableAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRegistration(int $daysUntilSession = 10): array
{
    $level = Level::factory()->create();
    $user = User::factory()->withLevel($level)->create();
    $admin = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card = Card::factory()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'sessions_remaining' => 5,
        'status' => CardStatus::Active,
        'expires_at' => now()->addMonths(6),
    ]);
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'scheduled_at' => now()->addDays($daysUntilSession),
        'status' => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);
    $registration = Registration::create([
        'user_id' => $user->id,
        'conversation_table_id' => $table->id,
        'card_id' => $card->id,
        'status' => RegistrationStatus::Registered,
        'registered_at' => now()->subHour(),
    ]);
    $card->decrement('sessions_remaining');

    return compact('user', 'admin', 'card', 'table', 'registration');
}

it('allows cancellation at J-3 business days and recredits the card', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    [
        'user' => $user,
        'admin' => $admin,
        'card' => $card,
        'registration' => $registration,
    ] = makeRegistration(daysUntilSession: 10);

    $sessionsBefore = $card->sessions_remaining;

    $result = app(CancelRegistrationAction::class)->execute($registration, $user);

    expect($result->status)->toBe(RegistrationStatus::Cancelled);
    expect($result->cancelled_by)->toBe($user->id);
    expect($card->fresh()->sessions_remaining)->toBe($sessionsBefore + 1);
});

it('refuses cancellation at J-2 business days', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    // Créer une session dans 2 jours ouvrables (insuffisant)
    ['registration' => $registration, 'user' => $user] = makeRegistration(daysUntilSession: 2);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'cancellation_deadline_passed');
});

it('allows admin override even at J-1', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    [
        'admin' => $admin,
        'card' => $card,
        'registration' => $registration,
    ] = makeRegistration(daysUntilSession: 1);

    $sessionsBefore = $card->sessions_remaining;

    $result = app(CancelRegistrationAction::class)->execute($registration, $admin, adminOverride: true);

    expect($result->status)->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe($sessionsBefore + 1);
});

it('recredits the card on cancellation', function () {
    ['user' => $user, 'card' => $card, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    $before = $card->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($registration, $user);

    expect($card->fresh()->sessions_remaining)->toBe($before + 1);
});
