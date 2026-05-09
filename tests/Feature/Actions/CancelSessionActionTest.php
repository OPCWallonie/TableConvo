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
use App\Notifications\SessionCancelledNotification;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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

// ─────────────────────────────────────────────────────────────
// Nouveaux tests Phase 6 Step A
// ─────────────────────────────────────────────────────────────

it('throws session_not_cancellable when session is already cancelled', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'status'   => SessionStatus::Cancelled,
    ]);

    expect(fn () => app(CancelSessionAction::class)->execute($table, $admin, 'Test'))
        ->toThrow(RuntimeException::class, 'session_not_cancellable');
});

it('throws session_not_cancellable when session status is completed', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'status'   => SessionStatus::Completed,
    ]);

    expect(fn () => app(CancelSessionAction::class)->execute($table, $admin, 'Test'))
        ->toThrow(RuntimeException::class, 'session_not_cancellable');
});

it('throws session_already_passed when scheduled_at is in the past', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->subDay(),
    ]);

    expect(fn () => app(CancelSessionAction::class)->execute($table, $admin, 'Test'))
        ->toThrow(RuntimeException::class, 'session_already_passed');
});

it('does NOT recredit sessions_remaining or extend expires_at for inactive card', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
    ]);

    $user    = User::factory()->withLevel($level)->create();
    $card    = makeCardForUser($user, [
        'expires_at'         => now()->subDays(5),
        'status'             => CardStatus::Expired,
        'sessions_remaining' => 5,
    ]);
    makeConfirmedRegistration($table, $card); // decrements to 4

    $remainingBefore = $card->fresh()->sessions_remaining;
    $expiresBefore   = $card->fresh()->expires_at->toDateString();

    app(CancelSessionAction::class)->execute($table, $admin, 'Test bug fix');

    expect($card->fresh()->sessions_remaining)->toBe($remainingBefore);
    expect($card->fresh()->expires_at->toDateString())->toBe($expiresBefore);
});

it('cancels waitlist registrations without recrediting any card', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(5),
        'max_participants' => 1,
    ]);

    $user = User::factory()->withLevel($level)->create();
    $card = makeCardForUser($user, ['sessions_remaining' => 5]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subHour(),
        'waitlist_position'     => 1,
    ]);

    $remainingBefore = $card->fresh()->sessions_remaining;

    app(CancelSessionAction::class)->execute($table, $admin, 'Test waitlist');

    expect(Registration::where('conversation_table_id', $table->id)->first()->status)
        ->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe($remainingBefore);
});

it('dispatches SessionCancelledNotification with recredit_and_extend type for near-expiry active card', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->post_cancellation_extension_threshold_days = 30;
    $settings->post_cancellation_card_extension_days      = 30;
    $settings->save();

    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $user  = User::factory()->withLevel($level)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);
    $card = makeCardForUser($user, ['expires_at' => now()->addDays(15), 'status' => CardStatus::Active]);
    makeConfirmedRegistration($table, $card);

    app(CancelSessionAction::class)->execute($table, $admin, 'Test notif');

    Notification::assertSentTo(
        $user,
        SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'recredit_and_extend'
    );
});

it('dispatches SessionCancelledNotification with recredit_only type for far-expiry active card', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->post_cancellation_extension_threshold_days = 30;
    $settings->save();

    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $user  = User::factory()->withLevel($level)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);
    $card = makeCardForUser($user, ['expires_at' => now()->addDays(90), 'status' => CardStatus::Active]);
    makeConfirmedRegistration($table, $card);

    app(CancelSessionAction::class)->execute($table, $admin, 'Test notif');

    Notification::assertSentTo(
        $user,
        SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'recredit_only'
    );
});

it('dispatches SessionCancelledNotification with waitlist_notice type for waitlisted registration', function () {
    Notification::fake();

    $level = Level::factory()->create();
    $admin = User::factory()->create();
    $user  = User::factory()->withLevel($level)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subHour(),
        'waitlist_position'     => 1,
    ]);

    app(CancelSessionAction::class)->execute($table, $admin, 'Test notif');

    Notification::assertSentTo(
        $user,
        SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'waitlist_notice'
    );
});
