<?php

use App\Actions\Registration\CancelRegistrationAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Events\RegistrationCancelled;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use Illuminate\Support\Facades\Event;
use App\Models\Registration;
use App\Models\User;
use App\Settings\BookingSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Crée un contexte complet : user avec carte active, session planifiée, inscription Registered.
 * @param int $daysUntilSession  jours calendaires jusqu'à la session (défaut 10, dans les délais)
 * @param Carbon|null $scheduledAt  date exacte de la session (prioritaire sur daysUntilSession)
 */
function makeRegistration(int $daysUntilSession = 10, ?Carbon $scheduledAt = null): array
{
    $level = Level::factory()->create();
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'sessions_remaining' => 5,
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => $scheduledAt ?? now()->addDays($daysUntilSession),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);
    $registration = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subHour(),
    ]);
    $card->decrement('sessions_remaining');

    return compact('user', 'card', 'table', 'registration');
}

/** Crée et retourne un utilisateur avec le rôle admin. */
function makeAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

// ─────────────────────────────────────────────────────────────
// Cas nominaux
// ─────────────────────────────────────────────────────────────

it('cancels a registered registration before deadline', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    $result = app(CancelRegistrationAction::class)->execute($registration, $user);

    expect($result->status)->toBe(RegistrationStatus::Cancelled);
    expect($result->cancelled_by)->toBe($user->id);
    expect($result->cancelled_at)->not->toBeNull();
});

it('recredits sessions_remaining on the card when cancelling before deadline', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'card' => $card, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    $before = $card->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($registration, $user);

    expect($card->fresh()->sessions_remaining)->toBe($before + 1);
});

// ─────────────────────────────────────────────────────────────
// Recréditation conditionnelle
// ─────────────────────────────────────────────────────────────

it('does not recredit if card is expired', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'card' => $card, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    // Expirer la carte après la création de l'inscription
    $card->update(['expires_at' => now()->subDay()]);
    $before = $card->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($registration, $user);

    // Annulation réussie, mais pas de recréditation
    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe($before);
});

it('does not recredit if card status is not Active', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'card' => $card, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    $card->update(['status' => CardStatus::Expired]);
    $before = $card->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($registration, $user);

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe($before);
});

// ─────────────────────────────────────────────────────────────
// Deadline
// ─────────────────────────────────────────────────────────────

it('throws deadline_passed when cancelling too late as a regular user', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    // Session dans 2 jours → deadline en arrière de 3 jours ouvrables → passée
    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 2);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'deadline_passed');
});

it('admin can cancel even after deadline has passed', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    // Session demain → deadline largement dépassée pour un user normal
    ['card' => $card, 'registration' => $registration] = makeRegistration(daysUntilSession: 1);
    $admin = makeAdmin();
    $before = $card->fresh()->sessions_remaining;

    $result = app(CancelRegistrationAction::class)->execute($registration, $admin);

    expect($result->status)->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe($before + 1);
});

// ─────────────────────────────────────────────────────────────
// Codes d'erreur cannot_cancel / session_unavailable
// ─────────────────────────────────────────────────────────────

it('throws cannot_cancel if registration is already Cancelled', function () {
    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);
    $registration->update(['status' => RegistrationStatus::Cancelled]);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'cannot_cancel');
});

it('throws cannot_cancel if registration is Attended', function () {
    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);
    $registration->update(['status' => RegistrationStatus::Attended]);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'cannot_cancel');
});

it('throws session_unavailable if the session has been cancelled by admin', function () {
    ['user' => $user, 'table' => $table, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);
    $table->update(['status' => SessionStatus::Cancelled]);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'session_unavailable');
});

it('throws session_unavailable if the session is in the past', function () {
    ['user' => $user, 'table' => $table, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);
    $table->update(['scheduled_at' => now()->subDay()]);

    expect(fn () => app(CancelRegistrationAction::class)->execute($registration, $user))
        ->toThrow(RuntimeException::class, 'session_unavailable');
});

// ─────────────────────────────────────────────────────────────
// Waitlist
// ─────────────────────────────────────────────────────────────

it('handles waitlist cancellation without reCrediting any card', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    $level = Level::factory()->create();
    $user  = User::factory()->withLevel($level)->create(); // pas de carte
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(10),
        'status'       => SessionStatus::Scheduled,
    ]);
    $waitlistReg = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subHour(),
        'waitlist_position'     => 1,
    ]);

    $result = app(CancelRegistrationAction::class)->execute($waitlistReg, $user);

    expect($result->status)->toBe(RegistrationStatus::Cancelled);
    expect($result->card_id)->toBeNull();
});

// ─────────────────────────────────────────────────────────────
// Journal d'audit
// ─────────────────────────────────────────────────────────────

it('logs activity with the cancelling user as causer', function () {
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    app(CancelRegistrationAction::class)->execute($registration, $user);

    $log = Activity::where('causer_id', $user->id)
        ->where('description', 'Inscription annulée')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($user->id);
});

// ─────────────────────────────────────────────────────────────
// Fériés belges — calcul de deadline
// ─────────────────────────────────────────────────────────────

it('correctly computes deadline when a Belgian holiday falls between today and the session', function () {
    // Session le lundi 4 mai 2026 (juste après 1er mai = Labour Day, vendredi)
    // cancellation_deadline_business_days = 3
    // subBusinessDays(2026-05-04, 3) :
    //   dim 3/05 skip, sam 2/05 skip, ven 1/05 (Labour Day) skip
    //   → jeu 30/04 (1), mer 29/04 (2), mar 28/04 (3)
    //   deadline = 2026-04-28 23:59:59

    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    // CAS 1 : annulation le 27 avril → avant la deadline → autorisée
    Carbon::setTestNow('2026-04-27 10:00:00');

    ['user' => $user, 'card' => $card, 'registration' => $registration] = makeRegistration(
        scheduledAt: Carbon::parse('2026-05-04 10:00:00')
    );

    $result = app(CancelRegistrationAction::class)->execute($registration, $user);
    expect($result->status)->toBe(RegistrationStatus::Cancelled);

    // CAS 2 : même configuration, nouvelle inscription, annulation le 29 avril → après deadline → refusée
    Carbon::setTestNow('2026-04-29 10:00:00');

    $level2 = Level::factory()->create();
    $user2  = User::factory()->withLevel($level2)->create();
    $order2 = Order::factory()->create(['user_id' => $user2->id]);
    $card2  = Card::factory()->create([
        'user_id'            => $user2->id,
        'order_id'           => $order2->id,
        'sessions_remaining' => 5,
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);
    $table2 = ConversationTable::factory()->create([
        'level_id'     => $level2->id,
        'scheduled_at' => Carbon::parse('2026-05-04 10:00:00'),
        'status'       => SessionStatus::Scheduled,
    ]);
    $reg2 = Registration::create([
        'user_id'               => $user2->id,
        'conversation_table_id' => $table2->id,
        'card_id'               => $card2->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subHour(),
    ]);
    $card2->decrement('sessions_remaining');

    expect(fn () => app(CancelRegistrationAction::class)->execute($reg2, $user2))
        ->toThrow(RuntimeException::class, 'deadline_passed');

    Carbon::setTestNow(); // reset impératif
});

// ─────────────────────────────────────────────────────────────
// Dispatch d'event
// ─────────────────────────────────────────────────────────────

it('dispatches RegistrationCancelled event when cancelling a Registered registration', function () {
    Event::fake();

    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['user' => $user, 'registration' => $registration] = makeRegistration(daysUntilSession: 10);

    app(CancelRegistrationAction::class)->execute($registration, $user);

    Event::assertDispatched(RegistrationCancelled::class, function (RegistrationCancelled $e) use ($registration) {
        return $e->registration->id === $registration->id
            && $e->cancelledByAdmin === false;
    });
});

it('does not dispatch RegistrationCancelled when cancelling a Waitlist registration', function () {
    Event::fake();

    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    $level = Level::factory()->create();
    $user  = User::factory()->withLevel($level)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(10),
        'status'       => SessionStatus::Scheduled,
    ]);
    $waitlistReg = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subHour(),
        'waitlist_position'     => 1,
    ]);

    app(CancelRegistrationAction::class)->execute($waitlistReg, $user);

    Event::assertNotDispatched(RegistrationCancelled::class);
});

it('dispatches RegistrationCancelled with cancelledByAdmin true when admin cancels', function () {
    Event::fake();

    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    ['registration' => $registration] = makeRegistration(daysUntilSession: 10);
    $admin = makeAdmin();

    app(CancelRegistrationAction::class)->execute($registration, $admin);

    Event::assertDispatched(RegistrationCancelled::class, function (RegistrationCancelled $e) {
        return $e->cancelledByAdmin === true;
    });
});
