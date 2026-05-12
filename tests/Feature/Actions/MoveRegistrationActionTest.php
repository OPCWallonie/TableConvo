<?php

use App\Actions\Registration\FindEligibleTargetSessionsAction;
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
use App\Notifications\RegistrationRedirectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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

    return compact('admin', 'user', 'level', 'tableA', 'tableB', 'registration', 'card');
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

// ─────────────────────────────────────────────────────────────
// Vérifications métier — nouvelle table
// ─────────────────────────────────────────────────────────────

it('throws target_table_not_scheduled when new table is Cancelled', function () {
    ['admin' => $admin, 'registration' => $registration, 'level' => $level] = makeMoveSetup();

    $cancelled = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(14),
        'status'       => SessionStatus::Cancelled,
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($registration, $cancelled, $admin))
        ->toThrow(RuntimeException::class, 'target_table_not_scheduled');
});

it('throws target_table_not_scheduled when new table is Completed', function () {
    ['admin' => $admin, 'registration' => $registration, 'level' => $level] = makeMoveSetup();

    $completed = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subDays(3),
        'status'       => SessionStatus::Completed,
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($registration, $completed, $admin))
        ->toThrow(RuntimeException::class, 'target_table_not_scheduled');
});

it('throws target_table_in_past when new table scheduled_at is in the past', function () {
    ['admin' => $admin, 'registration' => $registration, 'level' => $level] = makeMoveSetup();

    $past = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subDays(2),
        'status'       => SessionStatus::Scheduled,
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($registration, $past, $admin))
        ->toThrow(RuntimeException::class, 'target_table_in_past');
});

it('throws user_already_on_target_table when user has a Registered status on target', function () {
    ['admin' => $admin, 'user' => $user, 'tableA' => $tableA, 'tableB' => $tableB, 'registration' => $regA] = makeMoveSetup();

    // L'user est aussi Registered sur tableB
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $tableB->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subMinutes(30),
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($regA, $tableB, $admin))
        ->toThrow(RuntimeException::class, 'user_already_on_target_table');
});

it('throws user_already_on_target_table when user has a Waitlist status on target', function () {
    ['admin' => $admin, 'user' => $user, 'registration' => $regA, 'tableB' => $tableB] = makeMoveSetup();

    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $tableB->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subMinutes(30),
        'waitlist_position'     => 1,
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($regA, $tableB, $admin))
        ->toThrow(RuntimeException::class, 'user_already_on_target_table');
});

it('throws target_table_full when moving a Registered to a full table', function () {
    ['admin' => $admin, 'level' => $level, 'registration' => $regA] = makeMoveSetup();

    $full = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => now()->addDays(21),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 1,
    ]);

    // Remplir la table cible avec un autre user
    $other = User::factory()->withLevel($level)->create();
    Registration::create([
        'user_id'               => $other->id,
        'conversation_table_id' => $full->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subMinutes(10),
    ]);

    expect(fn () => app(MoveRegistrationAction::class)->execute($regA, $full, $admin))
        ->toThrow(RuntimeException::class, 'target_table_full');
});

it('does not throw target_table_full when moving a Waitlist registration to a full table', function () {
    ['admin' => $admin, 'level' => $level, 'registration' => $regA] = makeMoveSetup();

    $regA->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    $full = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => now()->addDays(21),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 1,
    ]);

    $other = User::factory()->withLevel($level)->create();
    Registration::create([
        'user_id'               => $other->id,
        'conversation_table_id' => $full->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subMinutes(10),
    ]);

    $result = app(MoveRegistrationAction::class)->execute($regA, $full, $admin);

    expect($result->conversation_table_id)->toBe($full->id);
    expect($result->status)->toBe(RegistrationStatus::Waitlist);
});

// ─────────────────────────────────────────────────────────────
// Repositionnement de la waitlist
// ─────────────────────────────────────────────────────────────

it('assigns next waitlist position on target table when moving a waitlisted registration', function () {
    ['admin' => $admin, 'level' => $level, 'registration' => $regA, 'tableB' => $tableB] = makeMoveSetup();

    $regA->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    // tableB a déjà 2 waitlisters (positions 1 et 2)
    $otherUser1 = User::factory()->withLevel($level)->create();
    $otherUser2 = User::factory()->withLevel($level)->create();
    Registration::create([
        'user_id' => $otherUser1->id, 'conversation_table_id' => $tableB->id,
        'status'  => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(20), 'waitlist_position' => 1,
    ]);
    Registration::create([
        'user_id' => $otherUser2->id, 'conversation_table_id' => $tableB->id,
        'status'  => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(10), 'waitlist_position' => 2,
    ]);

    $result = app(MoveRegistrationAction::class)->execute($regA, $tableB, $admin);

    // doit prendre la position 3 sur tableB
    expect($result->waitlist_position)->toBe(3);
});

it('decrements waitlist positions of remaining users on source table after move', function () {
    ['admin' => $admin, 'level' => $level, 'tableA' => $tableA, 'tableB' => $tableB] = makeMoveSetup();

    // 4 waitlisters sur tableA : positions 1, 2, 3, 4
    $users = [];
    $regs  = [];
    for ($i = 1; $i <= 4; $i++) {
        $u = User::factory()->withLevel($level)->create();
        $r = Registration::create([
            'user_id'               => $u->id,
            'conversation_table_id' => $tableA->id,
            'card_id'               => null,
            'status'                => RegistrationStatus::Waitlist,
            'registered_at'         => now()->subMinutes(50 - $i * 10),
            'waitlist_position'     => $i,
        ]);
        $users[] = $u;
        $regs[]  = $r;
    }

    // Déplacer celui en position 2 vers tableB
    app(MoveRegistrationAction::class)->execute($regs[1], $tableB, $admin);

    // tableA : positions 1 (inchangé), 2 (ancien 3), 2 (ancien 4)
    expect($regs[0]->fresh()->waitlist_position)->toBe(1); // inchangé
    expect($regs[1]->fresh()->conversation_table_id)->toBe($tableB->id); // déplacé
    expect($regs[2]->fresh()->waitlist_position)->toBe(2); // était 3 → devient 2
    expect($regs[3]->fresh()->waitlist_position)->toBe(3); // était 4 → devient 3

    // tableB : le déplacé prend la position 1
    expect($regs[1]->fresh()->waitlist_position)->toBe(1);
});

// ─────────────────────────────────────────────────────────────
// Décision niveau — volontairement permissif pour l'admin
// ─────────────────────────────────────────────────────────────

it('allows moving a registration to a table of a different level (admin discretion)', function () {
    ['admin' => $admin, 'registration' => $regA] = makeMoveSetup();

    $otherLevel = Level::factory()->create();
    $otherTable = ConversationTable::factory()->create([
        'level_id'     => $otherLevel->id,
        'scheduled_at' => now()->addDays(21),
        'status'       => SessionStatus::Scheduled,
    ]);

    $result = app(MoveRegistrationAction::class)->execute($regA, $otherTable, $admin);

    expect($result->conversation_table_id)->toBe($otherTable->id);
    expect($result->conversationTable->level_id)->toBe($otherLevel->id);
});

// ─────────────────────────────────────────────────────────────
// Étape C — Réorientation et FindEligibleTargetSessionsAction
// ─────────────────────────────────────────────────────────────

it('redirecting a waitlist registration keeps it in Waitlist status', function () {
    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    $result = app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin);

    expect($result->status)->toBe(RegistrationStatus::Waitlist);
    expect($result->conversation_table_id)->toBe($tableB->id);
});

it('redirected waitlist registration goes to the end of target waitlist (FIFO)', function () {
    ['admin' => $admin, 'level' => $level, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    // tableB already has 3 waitlisters
    for ($i = 1; $i <= 3; $i++) {
        $u = User::factory()->withLevel($level)->create();
        Registration::create([
            'user_id'               => $u->id,
            'conversation_table_id' => $tableB->id,
            'status'                => RegistrationStatus::Waitlist,
            'registered_at'         => now()->subMinutes(60 - $i * 10),
            'waitlist_position'     => $i,
        ]);
    }

    $result = app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin);

    expect($result->waitlist_position)->toBe(4);
});

it('eligible target sessions are filtered by exact same level', function () {
    ['registration' => $registration, 'level' => $level] = makeMoveSetup();

    $otherLevel = Level::factory()->create();

    ConversationTable::factory()->create([
        'level_id'     => $otherLevel->id,
        'scheduled_at' => now()->addDays(5),
        'status'       => SessionStatus::Scheduled,
    ]);

    $sameLevel = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(10),
        'status'       => SessionStatus::Scheduled,
    ]);

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    $results = app(FindEligibleTargetSessionsAction::class)->execute($registration);

    expect($results->pluck('id'))->toContain($sameLevel->id);
    expect($results->pluck('level_id')->unique()->toArray())->toBe([$level->id]);
});

it('eligible target sessions exclude the current session and past sessions', function () {
    ['registration' => $registration, 'level' => $level, 'tableA' => $tableA] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    $past = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subDays(1),
        'status'       => SessionStatus::Scheduled,
    ]);

    $future = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(10),
        'status'       => SessionStatus::Scheduled,
    ]);

    $results = app(FindEligibleTargetSessionsAction::class)->execute($registration);

    expect($results->pluck('id'))->not->toContain($tableA->id);
    expect($results->pluck('id'))->not->toContain($past->id);
    expect($results->pluck('id'))->toContain($future->id);
});

it('admin_redirect context sends RegistrationRedirectedNotification', function () {
    Notification::fake();

    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration, 'user' => $user] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin, 'admin_redirect');

    Notification::assertSentTo($user, RegistrationRedirectedNotification::class);
});

it('no notification is sent when context argument is omitted', function () {
    Notification::fake();

    ['admin' => $admin, 'tableB' => $tableB, 'registration' => $registration] = makeMoveSetup();

    $registration->update(['status' => RegistrationStatus::Waitlist, 'waitlist_position' => 1]);

    app(MoveRegistrationAction::class)->execute($registration, $tableB, $admin);

    Notification::assertNothingSent();
});
