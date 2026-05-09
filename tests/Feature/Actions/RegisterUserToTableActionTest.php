<?php

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

function makeUserWithCard(Level $level, int $sessionsRemaining = 5): User
{
    $user = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    Card::factory()->create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'sessions_remaining' => $sessionsRemaining,
        'status' => CardStatus::Active,
        'expires_at' => now()->addMonths(6),
    ]);
    return $user;
}

function makeTable(Level $level, int $maxParticipants = 8, int $daysAhead = 7): ConversationTable
{
    return ConversationTable::factory()->create([
        'level_id' => $level->id,
        'max_participants' => $maxParticipants,
        'scheduled_at' => now()->addDays($daysAhead),
        'status' => SessionStatus::Scheduled,
    ]);
}

// --- Happy path ---

it('registers user and decrements card sessions', function () {
    $level = Level::factory()->create();
    $user = makeUserWithCard($level, 5);
    $table = makeTable($level);

    $registration = app(RegisterUserToTableAction::class)->execute($user, $table);

    expect($registration->status)->toBe(RegistrationStatus::Registered);
    expect($user->fresh()->cards()->first()->sessions_remaining)->toBe(4);
    expect(Registration::where('user_id', $user->id)->count())->toBe(1);
});

// --- Règles de blocage ---

it('blocks when session is cancelled', function () {
    $level = Level::factory()->create();
    $user = makeUserWithCard($level);
    $table = makeTable($level);
    $table->update(['status' => SessionStatus::Cancelled]);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'session_not_open_for_registration');
});

it('blocks when user has no level', function () {
    $level = Level::factory()->create();
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    Card::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
    $table = makeTable($level);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'no_level');
});

it('blocks when user level does not match table level', function () {
    $level1 = Level::factory()->create();
    $level2 = Level::factory()->create();
    $user = makeUserWithCard($level1);
    $table = makeTable($level2);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'wrong_level');
});

it('blocks when registration deadline has passed', function () {
    $level = Level::factory()->create();
    $user = makeUserWithCard($level);
    $table = makeTable($level, 8, 0); // scheduled today = deadline dépassée (< 24h)
    $table->update(['scheduled_at' => now()->addHours(1)]);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'deadline_passed');
});

it('blocks when table is full', function () {
    $level = Level::factory()->create();
    $userA = makeUserWithCard($level);
    $userB = makeUserWithCard($level);
    $table = makeTable($level, 1); // max 1 participant

    app(RegisterUserToTableAction::class)->execute($userA, $table);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($userB, $table))
        ->toThrow(RuntimeException::class, 'table_full');
});

it('blocks when weekly limit is reached', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->max_registrations_per_week = 1;
    $settings->save();

    $level = Level::factory()->create();
    $user = makeUserWithCard($level, 10);

    $table1 = makeTable($level, 8, 3);
    $table2 = ConversationTable::factory()->create([
        'level_id' => $level->id,
        'scheduled_at' => now()->addDays(4),
        'status' => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);

    app(RegisterUserToTableAction::class)->execute($user, $table1);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table2))
        ->toThrow(RuntimeException::class, 'weekly_limit_reached');
});

it('blocks when future registrations limit is reached', function () {
    /** @var BookingSettings $settings */
    $settings = app(BookingSettings::class);
    $settings->max_future_registrations = 2;
    $settings->save();

    $level = Level::factory()->create();
    $user = makeUserWithCard($level, 10);

    $tables = ConversationTable::factory()->count(3)->create([
        'level_id' => $level->id,
        'status' => SessionStatus::Scheduled,
        'max_participants' => 8,
        'scheduled_at' => now()->addDays(14),
    ]);

    // Override scheduled_at to be on different weeks
    $tables[0]->update(['scheduled_at' => now()->addWeeks(2)]);
    $tables[1]->update(['scheduled_at' => now()->addWeeks(3)]);
    $tables[2]->update(['scheduled_at' => now()->addWeeks(4)]);

    app(RegisterUserToTableAction::class)->execute($user, $tables[0]);

    // Dépasse la limite hebdomadaire avec un autre user sur table1 → on utilise table2
    $user2 = makeUserWithCard($level, 10);
    app(RegisterUserToTableAction::class)->execute($user, $tables[1]);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $tables[2]))
        ->toThrow(RuntimeException::class, 'future_limit_reached');
});

it('blocks when user is already registered to this table', function () {
    $level = Level::factory()->create();
    $user = makeUserWithCard($level, 10);
    $table = makeTable($level);

    app(RegisterUserToTableAction::class)->execute($user, $table);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'already_registered');
});

it('blocks when user has no active card', function () {
    $level = Level::factory()->create();
    $user = User::factory()->withLevel($level)->create();
    $table = makeTable($level);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table))
        ->toThrow(RuntimeException::class, 'no_active_card');
});

// --- Waitlist ---

it('waitlist registration is allowed even without active card', function () {
    $level = Level::factory()->create();
    $user = User::factory()->withLevel($level)->create(); // pas de carte
    $table = makeTable($level);

    $registration = app(RegisterUserToTableAction::class)->execute($user, $table, forWaitlist: true);

    expect($registration->status)->toBe(RegistrationStatus::Waitlist);
    expect($registration->card_id)->toBeNull();
    expect($registration->waitlist_position)->toBe(1);
});

it('waitlist assigns sequential positions', function () {
    $level = Level::factory()->create();
    $userA = User::factory()->withLevel($level)->create();
    $userB = User::factory()->withLevel($level)->create();
    $table = makeTable($level);

    $regA = app(RegisterUserToTableAction::class)->execute($userA, $table, forWaitlist: true);
    $regB = app(RegisterUserToTableAction::class)->execute($userB, $table, forWaitlist: true);

    expect($regA->waitlist_position)->toBe(1);
    expect($regB->waitlist_position)->toBe(2);
});

it('waitlist blocks if already Registered on the same table', function () {
    $level = Level::factory()->create();
    $user = makeUserWithCard($level);
    $table = makeTable($level);

    app(RegisterUserToTableAction::class)->execute($user, $table); // inscription normale

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table, forWaitlist: true))
        ->toThrow(RuntimeException::class, 'already_registered');
});

it('waitlist blocks if already on Waitlist on the same table', function () {
    $level = Level::factory()->create();
    $user = User::factory()->withLevel($level)->create();
    $table = makeTable($level);

    app(RegisterUserToTableAction::class)->execute($user, $table, forWaitlist: true);

    expect(fn () => app(RegisterUserToTableAction::class)->execute($user, $table, forWaitlist: true))
        ->toThrow(RuntimeException::class, 'already_registered');
});
