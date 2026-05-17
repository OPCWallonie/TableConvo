<?php

use App\Actions\GlobalWaitlist\ReassignFromGlobalWaitlistAction;
use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ReassignedFromGlobalPoolNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeReassignSetup(): array
{
    $level  = Level::factory()->create(['code' => 'B2', 'sort_order' => 4]);
    $admin  = User::factory()->create();
    $user   = User::factory()->create(['level_id' => $level->id]);
    $target = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'max_participants' => 8,
        'scheduled_at'     => now()->addDays(7),
        'status'           => SessionStatus::Scheduled,
    ]);
    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    return compact('level', 'admin', 'user', 'target', 'entry');
}

// ─── Tests ──────────────────────────────────────────────────

it('creates a Registered registration when target has space and user has active card', function () {
    ['admin' => $admin, 'user' => $user, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $registration = app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    expect($registration->status)->toBe(RegistrationStatus::Registered);
    expect($registration->user_id)->toBe($user->id);
    expect($registration->conversation_table_id)->toBe($target->id);
});

it('creates a Waitlist registration when target is full', function () {
    ['admin' => $admin, 'user' => $user, 'level' => $level, 'entry' => $entry] = makeReassignSetup();

    // Table with max 1, already 1 registered
    $target = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'max_participants' => 1,
        'scheduled_at'     => now()->addDays(7),
        'status'           => SessionStatus::Scheduled,
    ]);
    $otherUser = User::factory()->create();
    Registration::factory()->create([
        'user_id'               => $otherUser->id,
        'conversation_table_id' => $target->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $registration = app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    expect($registration->status)->toBe(RegistrationStatus::Waitlist);
    expect($registration->card_id)->toBeNull();
});

it('creates a Waitlist registration when user has no active card', function () {
    ['admin' => $admin, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    // No active card for the user (no card created)
    $registration = app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    expect($registration->status)->toBe(RegistrationStatus::Waitlist);
    expect($registration->card_id)->toBeNull();
});

it('debits card sessions_remaining when registered', function () {
    ['admin' => $admin, 'user' => $user, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    $card = Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 4]);

    app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    expect($card->fresh()->sessions_remaining)->toBe(3);
});

it('marks entry as Reassigned with correct reassigned_to_registration_id', function () {
    ['admin' => $admin, 'user' => $user, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $registration = app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    $freshEntry = $entry->fresh();
    expect($freshEntry->status)->toBe(GlobalWaitlistEntryStatus::Reassigned);
    expect($freshEntry->reassigned_to_registration_id)->toBe($registration->id);
});

it('throws level_mismatch if target table level differs', function () {
    ['admin' => $admin, 'entry' => $entry] = makeReassignSetup();

    $otherLevel = Level::factory()->create(['code' => 'C1', 'sort_order' => 5]);
    $wrongTable = ConversationTable::factory()->create([
        'level_id'     => $otherLevel->id,
        'scheduled_at' => now()->addDays(7),
        'status'       => SessionStatus::Scheduled,
    ]);

    expect(fn () => app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $wrongTable, $admin))
        ->toThrow(RuntimeException::class, 'level_mismatch');
});

it('throws target_table_in_past', function () {
    ['admin' => $admin, 'level' => $level, 'entry' => $entry] = makeReassignSetup();

    $pastTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subDay(),
        'status'       => SessionStatus::Scheduled,
    ]);

    expect(fn () => app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $pastTable, $admin))
        ->toThrow(RuntimeException::class, 'target_table_in_past');
});

it('throws target_table_not_scheduled', function () {
    ['admin' => $admin, 'level' => $level, 'entry' => $entry] = makeReassignSetup();

    $cancelledTable = ConversationTable::factory()->cancelled()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(7),
    ]);

    expect(fn () => app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $cancelledTable, $admin))
        ->toThrow(RuntimeException::class, 'target_table_not_scheduled');
});

it('throws entry_not_pending if entry already reassigned', function () {
    ['admin' => $admin, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    $entry->update(['status' => GlobalWaitlistEntryStatus::Reassigned]);

    expect(fn () => app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin))
        ->toThrow(RuntimeException::class, 'entry_not_pending');
});

it('throws already_registered_on_target', function () {
    ['admin' => $admin, 'user' => $user, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $target->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    expect(fn () => app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin))
        ->toThrow(RuntimeException::class, 'already_registered_on_target');
});

it('dispatches ReassignedFromGlobalPoolNotification after commit', function () {
    Notification::fake();

    ['admin' => $admin, 'user' => $user, 'target' => $target, 'entry' => $entry] = makeReassignSetup();

    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    app(ReassignFromGlobalWaitlistAction::class)->execute($entry, $target, $admin);

    Notification::assertSentTo($user, ReassignedFromGlobalPoolNotification::class);
});
