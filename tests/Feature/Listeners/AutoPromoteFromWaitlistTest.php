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
use App\Models\Registration;
use App\Models\User;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function makeWaitlistAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

/**
 * Crée une table à capacité N avec 1 user Registered + carte active.
 * Retourne [level, table, userA, cardA, registrationA].
 */
function makeFullTable(int $capacity = 1): array
{
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->waitlist_auto_promote = true;
    $settings->save();

    $level = Level::factory()->create();

    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => $capacity,
    ]);

    $userA  = User::factory()->withLevel($level)->create();
    $orderA = Order::factory()->create(['user_id' => $userA->id]);
    $cardA  = Card::factory()->create([
        'user_id'            => $userA->id,
        'order_id'           => $orderA->id,
        'sessions_remaining' => 4, // déjà décrémenté
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);

    $regA = Registration::create([
        'user_id'               => $userA->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $cardA->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subHour(),
    ]);

    return compact('level', 'table', 'userA', 'cardA', 'regA');
}

function addWaitlister(ConversationTable $table, Level $level, bool $withCard = true, int $position = 1): array
{
    $user  = User::factory()->withLevel($level)->create();
    $card  = null;

    if ($withCard) {
        $order = Order::factory()->create(['user_id' => $user->id]);
        $card  = Card::factory()->create([
            'user_id'            => $user->id,
            'order_id'           => $order->id,
            'sessions_remaining' => 5,
            'status'             => CardStatus::Active,
            'expires_at'         => now()->addMonths(6),
        ]);
    }

    $reg = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subMinutes($position * 5),
        'waitlist_position'     => $position,
    ]);

    return compact('user', 'card', 'reg');
}

// ─────────────────────────────────────────────────────────────
// 1. Promotion automatique après annulation d'un Registered
// ─────────────────────────────────────────────────────────────

it('promotes first waitlisted user when a registered cancellation frees a spot', function () {
    $admin = makeWaitlistAdmin();
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);
    ['user' => $userB, 'card' => $cardB, 'reg' => $regB] = addWaitlister($table, $level, withCard: true, position: 1);

    $beforeB = $cardB->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    expect($regB->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($regB->fresh()->card_id)->not->toBeNull();
    expect($regB->fresh()->waitlist_position)->toBeNull();
    expect($cardB->fresh()->sessions_remaining)->toBe($beforeB - 1);
});

// ─────────────────────────────────────────────────────────────
// 2. Setting désactivé → pas de promotion
// ─────────────────────────────────────────────────────────────

it('does nothing when waitlist_auto_promote setting is false', function () {
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);

    // Override APRÈS makeFullTable qui met waitlist_auto_promote = true
    $settings = app(BookingSettings::class);
    $settings->waitlist_auto_promote = false;
    $settings->save();

    ['user' => $userB, 'reg' => $regB] = addWaitlister($table, $level, withCard: true, position: 1);

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    expect($regB->fresh()->status)->toBe(RegistrationStatus::Waitlist);
});

// ─────────────────────────────────────────────────────────────
// 3. Annulation d'une Waitlist → pas de promotion
// ─────────────────────────────────────────────────────────────

it('does not promote when a waitlist registration is cancelled', function () {
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);

    // UserB en waitlist
    ['user' => $userB, 'reg' => $regB] = addWaitlister($table, $level, withCard: true, position: 1);

    // UserC en waitlist (position 2)
    ['user' => $userC, 'reg' => $regC] = addWaitlister($table, $level, withCard: true, position: 2);

    // On annule B (waitlist), A est toujours Registered
    $regB->update(['status' => RegistrationStatus::Waitlist]); // s'assurer
    app(CancelRegistrationAction::class)->execute($regB, $userB);

    // A toujours Registered, C toujours Waitlist (pas de place libérée)
    expect($regA->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($regC->fresh()->status)->toBe(RegistrationStatus::Waitlist);
});

// ─────────────────────────────────────────────────────────────
// 4. Premier en waitlist sans carte → place reste libre, pas d'exception
// ─────────────────────────────────────────────────────────────

it('skips silently when first waitlisted user has no active card', function () {
    makeWaitlistAdmin();
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);
    ['user' => $userB, 'reg' => $regB] = addWaitlister($table, $level, withCard: false, position: 1);

    // L'action ne doit pas exploser
    app(CancelRegistrationAction::class)->execute($regA, $userA);

    // B reste en waitlist (pas de carte → promotion impossible)
    expect($regB->fresh()->status)->toBe(RegistrationStatus::Waitlist);

    // La place est libre (0 Registered sur la table)
    $registeredCount = $table->registrations()
        ->where('status', RegistrationStatus::Registered->value)
        ->count();
    expect($registeredCount)->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// 5. Seul le premier en waitlist est promu, les suivants restent
// ─────────────────────────────────────────────────────────────

it('promotes only the first in waitlist and leaves others unchanged', function () {
    makeWaitlistAdmin();
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);
    ['reg' => $regB] = addWaitlister($table, $level, withCard: true, position: 1);
    ['reg' => $regC] = addWaitlister($table, $level, withCard: true, position: 2);
    ['reg' => $regD] = addWaitlister($table, $level, withCard: true, position: 3);

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    expect($regB->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($regC->fresh()->status)->toBe(RegistrationStatus::Waitlist);
    expect($regD->fresh()->status)->toBe(RegistrationStatus::Waitlist);
});

// ─────────────────────────────────────────────────────────────
// 6. La carte du promu est décrémentée
// ─────────────────────────────────────────────────────────────

it('decrements sessions_remaining of the promoted user card', function () {
    makeWaitlistAdmin();
    ['level' => $level, 'table' => $table, 'userA' => $userA, 'regA' => $regA] = makeFullTable(1);
    ['card' => $cardB] = addWaitlister($table, $level, withCard: true, position: 1);

    $before = $cardB->fresh()->sessions_remaining;

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    expect($cardB->fresh()->sessions_remaining)->toBe($before - 1);
});

// ─────────────────────────────────────────────────────────────
// 7. Pas de promotion si table toujours pleine (cas défensif)
// ─────────────────────────────────────────────────────────────

it('does not dispatch RegistrationCancelled event when cancelling a Waitlist registration', function () {
    Event::fake();

    makeWaitlistAdmin();
    ['level' => $level, 'table' => $table] = makeFullTable(1);
    ['user' => $userB, 'reg' => $regB] = addWaitlister($table, $level, withCard: true, position: 1);

    app(CancelRegistrationAction::class)->execute($regB, $userB);

    Event::assertNotDispatched(RegistrationCancelled::class);
});
