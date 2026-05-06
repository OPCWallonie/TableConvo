<?php

use App\Actions\Session\CancelSessionAction;
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

function makeConfirmedRegistration(ConversationTable $table, Card $card): Registration
{
    $reg = Registration::create([
        'user_id' => $card->user_id,
        'conversation_table_id' => $table->id,
        'card_id' => $card->id,
        'status' => RegistrationStatus::Registered,
        'registered_at' => now()->subHour(),
    ]);
    $card->decrement('sessions_remaining');
    return $reg;
}

function makeCardForUser(User $user, array $cardAttributes = []): Card
{
    $order = Order::factory()->create(['user_id' => $user->id]);
    return Card::factory()->create(array_merge([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'sessions_remaining' => 5,
        'status' => CardStatus::Active,
        'expires_at' => now()->addMonths(6),
    ], $cardAttributes));
}

it('recredits all confirmed registrations when session is cancelled', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'status' => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
    ]);

    $users = User::factory()->count(3)->withLevel($level)->create();
    $cards = $users->map(fn ($u) => makeCardForUser($u));
    $cards->each(fn ($card) => makeConfirmedRegistration($table, $card));

    $sessionsBefore = $cards->map->fresh()->map->sessions_remaining->toArray();

    app(CancelSessionAction::class)->execute($table, $admin, 'Raison de test');

    $cards->each(function ($card, $i) use ($sessionsBefore) {
        expect($card->fresh()->sessions_remaining)->toBe($sessionsBefore[$i] + 1);
    });
});

it('extends validity only for cards expiring within threshold and not yet expired', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->post_cancellation_extension_threshold_days = 30;
    $settings->post_cancellation_card_extension_days = 30;
    $settings->save();

    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'status' => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
    ]);

    // Carte proche expiration (15 jours) → doit être prolongée
    $userNearExpiry = User::factory()->withLevel($level)->create();
    $cardNear = makeCardForUser($userNearExpiry, ['expires_at' => now()->addDays(15), 'status' => CardStatus::Active]);
    makeConfirmedRegistration($table, $cardNear);

    // Carte loin de l'expiration (90 jours) → ne doit PAS être prolongée
    $userFarExpiry = User::factory()->withLevel($level)->create();
    $cardFar = makeCardForUser($userFarExpiry, ['expires_at' => now()->addDays(90), 'status' => CardStatus::Active]);
    makeConfirmedRegistration($table, $cardFar);

    // Carte déjà expirée → ne doit PAS être prolongée
    $userExpired = User::factory()->withLevel($level)->create();
    $cardExpired = makeCardForUser($userExpired, [
        'expires_at' => now()->subDays(5),
        'status' => CardStatus::Expired,
    ]);
    makeConfirmedRegistration($table, $cardExpired);

    $nearBefore = $cardNear->expires_at->copy();
    $farBefore = $cardFar->expires_at->copy();
    $expiredBefore = $cardExpired->expires_at->copy();

    app(CancelSessionAction::class)->execute($table, $admin, 'Test extension');

    expect($cardNear->fresh()->expires_at->toDateString())
        ->toBe($nearBefore->addDays(30)->toDateString(), 'La carte proche expiration doit être prolongée');

    expect($cardFar->fresh()->expires_at->toDateString())
        ->toBe($farBefore->toDateString(), 'La carte loin d\'expiration ne doit PAS être prolongée');

    expect($cardExpired->fresh()->expires_at->toDateString())
        ->toBe($expiredBefore->toDateString(), 'La carte déjà expirée ne doit PAS être prolongée');
});

it('marks session as cancelled', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'status' => SessionStatus::Scheduled,
    ]);

    $result = app(CancelSessionAction::class)->execute($table, $admin, 'Annulation test');

    expect($result->status)->toBe(SessionStatus::Cancelled);
    expect($result->cancellation_reason)->toBe('Annulation test');
});
