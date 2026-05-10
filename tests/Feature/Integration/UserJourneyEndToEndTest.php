<?php

use App\Actions\Registration\CancelRegistrationAction;
use App\Actions\Registration\CheckRegistrationRulesAction;
use App\Actions\Registration\RegisterUserToTableAction;
use App\Actions\User\AssignLevelAction;
use App\Actions\User\RequestLevelInterviewAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\NotifyAdminOfLevelInterviewNeeded;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Scénario 1 — Utilisateur sans niveau : blocage + interview
// ─────────────────────────────────────────────────────────────

it('user without level is blocked from registering and interview request is sent once to admins', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $level = Level::factory()->create(['code' => 'B1']);
    $userNoLevel = User::factory()->create(['level_id' => null]);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
    ]);

    // Registration attempt → blocked
    $action = app(RegisterUserToTableAction::class);
    expect(fn () => $action->execute($userNoLevel, $table))
        ->toThrow(RuntimeException::class, 'no_level');

    // Admin is notified via RequestLevelInterviewAction
    $interviewAction = app(RequestLevelInterviewAction::class);
    $interviewAction->execute($userNoLevel);

    Notification::assertSentTo($admin, NotifyAdminOfLevelInterviewNeeded::class);
    expect($userNoLevel->fresh()->interview_requested_at)->not->toBeNull();

    // Second call is idempotent — no duplicate notification
    $interviewAction->execute($userNoLevel);
    Notification::assertCount(1);
});

// ─────────────────────────────────────────────────────────────
// Scénario 2 — Journey complet : niveau → inscription → annulation
// ─────────────────────────────────────────────────────────────

it('assigning a level unblocks registration, session is consumed then restored on timely cancellation', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $level   = Level::factory()->create(['code' => 'A2']);
    $user    = User::factory()->create(['level_id' => null]);
    $order   = Order::factory()->create(['user_id' => $user->id]);
    $card    = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'status'             => CardStatus::Active,
        'sessions_remaining' => 5,
        'expires_at'         => now()->addMonths(6),
    ]);

    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours       = 24;
    $settings->cancellation_deadline_business_days = 3;
    $settings->max_registrations_per_week        = 2;
    $settings->max_future_registrations          = 5;
    $settings->save();

    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => 8,
    ]);

    // 1 — Sans niveau, impossible de s'inscrire
    $registerAction = app(RegisterUserToTableAction::class);
    expect(fn () => $registerAction->execute($user, $table))
        ->toThrow(RuntimeException::class, 'no_level');

    // 2 — Admin attribue le niveau B1
    app(AssignLevelAction::class)->execute($user, $level, $admin);
    $user = $user->fresh();
    expect($user->level_id)->toBe($level->id);
    expect($user->level_assigned_at)->not->toBeNull();

    // 3 — Inscription réussie, carte décrémentée
    $registration = $registerAction->execute($user, $table);
    expect($registration->status)->toBe(RegistrationStatus::Registered);
    expect($card->fresh()->sessions_remaining)->toBe(4);

    // 4 — Annulation dans les délais : session restituée
    $cancelAction = app(CancelRegistrationAction::class);
    $cancelAction->execute($registration, $user);

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($card->fresh()->sessions_remaining)->toBe(5);
});

// ─────────────────────────────────────────────────────────────
// Scénario 3 — Cycle de vie complet avec voyage dans le temps
// ─────────────────────────────────────────────────────────────

it('card expires via time travel blocking new registrations while leaving sessions_remaining intact', function () {
    $level = Level::factory()->create(['code' => 'C1']);
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'status'             => CardStatus::Active,
        'sessions_remaining' => 10,
        'expires_at'         => now()->addDays(30),
    ]);

    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->max_registrations_per_week  = 1;
    $settings->max_future_registrations    = 5;
    $settings->save();

    // Table in the future (well within card validity)
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => 8,
    ]);

    $registerAction = app(RegisterUserToTableAction::class);
    $registerAction->execute($user, $table);
    expect($card->fresh()->sessions_remaining)->toBe(9);

    // Travel 35 days: card validity is now in the past
    $this->travel(35)->days();

    // Card still marked Active until the command runs
    expect($card->fresh()->status)->toBe(CardStatus::Active);

    // Command expires the card
    $this->artisan('cards:expire')->assertSuccessful();
    expect($card->fresh()->status)->toBe(CardStatus::Expired);

    // sessions_remaining untouched (lost per business rule — no auto-refund on expiry)
    expect($card->fresh()->sessions_remaining)->toBe(9);

    // Attempting a new registration is blocked: no active card
    $table2 = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => 8,
    ]);

    expect(fn () => $registerAction->execute($user->fresh(), $table2))
        ->toThrow(RuntimeException::class, 'no_active_card');
});
