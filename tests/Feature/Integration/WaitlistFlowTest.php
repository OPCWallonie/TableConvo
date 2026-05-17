<?php

use App\Actions\Registration\CancelRegistrationAction;
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
use App\Notifications\RegistrationCancelledByAdminNotification;
use App\Notifications\UserPromotedFromWaitlistNotification;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function makeFlowAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makeFlowSettings(bool $autoPromote = true): void
{
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->waitlist_auto_promote = $autoPromote;
    $settings->save();
}

function makeFlowTable(Level $level, int $capacity = 1): ConversationTable
{
    return ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => $capacity,
    ]);
}

function addRegistered(User $user, ConversationTable $table): array
{
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'sessions_remaining' => 5,
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);
    $reg = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subHour(),
    ]);
    $card->decrement('sessions_remaining'); // simule la consommation initiale
    return compact('card', 'reg');
}

function addWaitlisted(User $user, ConversationTable $table, int $position, bool $withCard = true): array
{
    $card = null;
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
        'registered_at'         => now()->subMinutes($position * 10),
        'waitlist_position'     => $position,
    ]);
    return compact('card', 'reg');
}

// ─────────────────────────────────────────────────────────────
// Test 1 — Cycle complet auto-promotion (user self-cancel)
// ─────────────────────────────────────────────────────────────

it('promotes waitlisted user and recredits cards when a registered user self-cancels', function () {
    Notification::fake();
    makeFlowSettings(autoPromote: true);

    $level = Level::factory()->create();
    $table = makeFlowTable($level, capacity: 2);

    $userA = User::factory()->withLevel($level)->create();
    $userB = User::factory()->withLevel($level)->create();
    $userC = User::factory()->withLevel($level)->create();

    ['card' => $cardA, 'reg' => $regA] = addRegistered($userA, $table);
    ['card' => $cardB, 'reg' => $regB] = addRegistered($userB, $table);
    ['card' => $cardC, 'reg' => $regC] = addWaitlisted($userC, $table, position: 1, withCard: true);

    $beforeA = $cardA->fresh()->sessions_remaining;
    $beforeC = $cardC->fresh()->sessions_remaining;

    // User A annule sa propre inscription
    app(CancelRegistrationAction::class)->execute($regA, $userA);

    // A : annulée, carte recréditée
    expect($regA->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($regA->fresh()->cancelled_at)->not->toBeNull();
    expect($cardA->fresh()->sessions_remaining)->toBe($beforeA + 1);

    // C : promue, card_id renseigné, position effacée, carte décrémentée
    expect($regC->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($regC->fresh()->card_id)->not->toBeNull();
    expect($regC->fresh()->waitlist_position)->toBeNull();
    expect($cardC->fresh()->sessions_remaining)->toBe($beforeC - 1);

    // Notification de promotion envoyée à C
    Notification::assertSentTo($userC, UserPromotedFromWaitlistNotification::class);

    // Pas de notif admin→user pour une auto-annulation
    Notification::assertNotSentTo($userA, RegistrationCancelledByAdminNotification::class);
});

// ─────────────────────────────────────────────────────────────
// Test 2 — Admin annule sans waitlist → notification + pas de promotion
// ─────────────────────────────────────────────────────────────

it('sends admin cancellation notification and creates no new registration when no waitlist exists', function () {
    Notification::fake();
    makeFlowSettings(autoPromote: true);

    $admin = makeFlowAdmin();
    $level = Level::factory()->create();
    $table = makeFlowTable($level, capacity: 1);
    $userA = User::factory()->withLevel($level)->create();

    ['reg' => $regA] = addRegistered($userA, $table);

    app(CancelRegistrationAction::class)->execute($regA, $admin);

    // A est annulée
    expect($regA->fresh()->status)->toBe(RegistrationStatus::Cancelled);

    // Notification d'annulation admin envoyée à A
    Notification::assertSentTo($userA, RegistrationCancelledByAdminNotification::class);

    // Aucune nouvelle registration créée (toujours 1 en DB = la cancelled)
    expect(
        Registration::where('conversation_table_id', $table->id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->count()
    )->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// Test 3 — Premier en waitlist sans carte → place reste libre
// ─────────────────────────────────────────────────────────────

it('leaves the spot open when the first waitlisted user has no active card', function () {
    Notification::fake();
    makeFlowSettings(autoPromote: true);

    $level = Level::factory()->create();
    $table = makeFlowTable($level, capacity: 1);
    $userA = User::factory()->withLevel($level)->create();
    $userB = User::factory()->withLevel($level)->create(); // sans carte

    ['reg' => $regA] = addRegistered($userA, $table);
    ['reg' => $regB] = addWaitlisted($userB, $table, position: 1, withCard: false);

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    // A annulée
    expect($regA->fresh()->status)->toBe(RegistrationStatus::Cancelled);

    // B reste en waitlist (pas de carte → promotion impossible → FIFO respecté)
    expect($regB->fresh()->status)->toBe(RegistrationStatus::Waitlist);

    // Place libre (aucun Registered sur la table)
    expect(
        $table->registrations()->where('status', RegistrationStatus::Registered->value)->count()
    )->toBe(0);

    // Aucune notification de promotion
    Notification::assertNotSentTo($userB, UserPromotedFromWaitlistNotification::class);
});

// ─────────────────────────────────────────────────────────────
// Test 4 — Déplacement d'un waitlister préserve l'ordre FIFO source
// ─────────────────────────────────────────────────────────────

it('preserves FIFO order on source table when moving a waitlist registration to another table', function () {
    Notification::fake();

    $admin = makeFlowAdmin();
    $level = Level::factory()->create();

    $tableA = makeFlowTable($level, capacity: 8);
    $tableB = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(14),
        'max_participants' => 8,
    ]);

    $userB = User::factory()->withLevel($level)->create();
    $userC = User::factory()->withLevel($level)->create();
    $userD = User::factory()->withLevel($level)->create();

    ['reg' => $regB] = addWaitlisted($userB, $tableA, position: 1);
    ['reg' => $regC] = addWaitlisted($userC, $tableA, position: 2);
    ['reg' => $regD] = addWaitlisted($userD, $tableA, position: 3);

    // Admin déplace C (position 2) de tableA vers tableB
    app(MoveRegistrationAction::class)->execute($regC, $tableB, $admin);

    // Table A : B reste en 1, D décale de 3 → 2
    expect($regB->fresh()->waitlist_position)->toBe(1);
    expect($regD->fresh()->waitlist_position)->toBe(2);

    // Table B : C prend la position 1
    expect($regC->fresh()->conversation_table_id)->toBe($tableB->id);
    expect($regC->fresh()->waitlist_position)->toBe(1);

    // Aucune notification (déplacement admin, pas d'annulation ni promotion)
    Notification::assertNothingSent();
});

// ─────────────────────────────────────────────────────────────
// Test 5 — Admin annule + auto-promotion → 2 notifications distinctes
// ─────────────────────────────────────────────────────────────

it('sends two distinct notifications when admin cancellation triggers auto-promotion', function () {
    Notification::fake();
    makeFlowSettings(autoPromote: true);

    $admin = makeFlowAdmin();
    $level = Level::factory()->create();
    $table = makeFlowTable($level, capacity: 1);

    $userA = User::factory()->withLevel($level)->create();
    $userB = User::factory()->withLevel($level)->create();

    ['reg' => $regA] = addRegistered($userA, $table);
    ['card' => $cardB, 'reg' => $regB] = addWaitlisted($userB, $table, position: 1, withCard: true);

    $beforeB = $cardB->fresh()->sessions_remaining;

    // Admin annule A
    app(CancelRegistrationAction::class)->execute($regA, $admin);

    // A : annulée
    expect($regA->fresh()->status)->toBe(RegistrationStatus::Cancelled);

    // B : promue
    expect($regB->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($cardB->fresh()->sessions_remaining)->toBe($beforeB - 1);

    // Notification 1 : A reçoit la notif d'annulation admin
    Notification::assertSentTo($userA, RegistrationCancelledByAdminNotification::class);

    // Notification 2 : B reçoit la notif de promotion
    Notification::assertSentTo($userB, UserPromotedFromWaitlistNotification::class);

    // Exactement 2 notifications au total (pas une de plus)
    Notification::assertCount(2);
});
