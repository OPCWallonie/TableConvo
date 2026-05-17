<?php

use App\Actions\GlobalWaitlist\MoveToGlobalWaitlistAction;
use App\Enums\CardStatus;
use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Enums\RegistrationStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\MovedToGlobalPoolNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeMoveToPoolSetup(bool $withLevel = true): array
{
    $level = Level::factory()->create(['code' => 'B1', 'sort_order' => 3]);
    $admin = User::factory()->create();
    $user  = User::factory()->create(['level_id' => $withLevel ? $level->id : null]);
    $table = ConversationTable::factory()->create(['level_id' => $level->id]);

    return compact('level', 'admin', 'user', 'table');
}

// ─── Tests ──────────────────────────────────────────────────

it('moves a Registered registration to global pool', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table, 'level' => $level] = makeMoveToPoolSetup();

    $card         = Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);
    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $entry = app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    );

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Pending);
    expect($entry->source)->toBe(GlobalWaitlistSource::AdminRemovedWaitlist);
    expect($entry->user_id)->toBe($user->id);
    expect($entry->level_id)->toBe($level->id);
});

it('moves a Waitlist registration to global pool', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 1,
        'card_id'               => null,
    ]);

    $entry = app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    );

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Pending);
});

it('recredits sessions_remaining on active card when moving Registered with recreditCard=true', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $card         = Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 3]);
    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
        null,
        true,
    );

    expect($card->fresh()->sessions_remaining)->toBe(4);
});

it('does not recredit if card is inactive', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $card = Card::factory()->create([
        'user_id'            => $user->id,
        'sessions_remaining' => 3,
        'status'             => CardStatus::Expired,
        'expires_at'         => now()->subDay(),
    ]);
    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
        null,
        true,
    );

    expect($card->fresh()->sessions_remaining)->toBe(3);

    $log = \Spatie\Activitylog\Models\Activity::forSubject($registration)->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('Recréditation impossible');
});

it('does not recredit if registration was on waitlist', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $card = Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 1,
    ]);

    app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
        null,
        true,
    );

    expect($card->fresh()->sessions_remaining)->toBe(5);
});

it('shifts FIFO positions on the source session after waitlist removal', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $user2 = User::factory()->create(['level_id' => $user->level_id]);
    $user3 = User::factory()->create(['level_id' => $user->level_id]);

    $reg1 = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 1,
        'card_id'               => null,
    ]);
    $reg2 = Registration::factory()->create([
        'user_id'               => $user2->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 2,
        'card_id'               => null,
    ]);
    $reg3 = Registration::factory()->create([
        'user_id'               => $user3->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 3,
        'card_id'               => null,
    ]);

    app(MoveToGlobalWaitlistAction::class)->execute(
        $reg1,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    );

    expect($reg2->fresh()->waitlist_position)->toBe(1);
    expect($reg3->fresh()->waitlist_position)->toBe(2);
});

it('requires admin_reason when source is AdminCancelledRegistration', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    expect(fn () => app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminCancelledRegistration,
        null,
    ))->toThrow(RuntimeException::class, 'admin_reason_required');
});

it('throws cannot_move_to_pool if registration is Cancelled', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Cancelled,
    ]);

    expect(fn () => app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    ))->toThrow(RuntimeException::class, 'cannot_move_to_pool');
});

it('throws user_level_missing if user has no level', function () {
    ['admin' => $admin, 'table' => $table] = makeMoveToPoolSetup();

    $userNoLevel  = User::factory()->create(['level_id' => null]);
    $registration = Registration::factory()->create([
        'user_id'               => $userNoLevel->id,
        'conversation_table_id' => $table->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    expect(fn () => app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    ))->toThrow(RuntimeException::class, 'user_level_missing');
});

it('dispatches MovedToGlobalPoolNotification after commit', function () {
    Notification::fake();

    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $card         = Card::factory()->create(['user_id' => $user->id]);
    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    );

    Notification::assertSentTo($user, MovedToGlobalPoolNotification::class);
});

it('logs activity on the new entry', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeMoveToPoolSetup();

    $card         = Card::factory()->create(['user_id' => $user->id]);
    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $entry = app(MoveToGlobalWaitlistAction::class)->execute(
        $registration,
        $admin,
        GlobalWaitlistSource::AdminRemovedWaitlist,
    );

    $logs = \Spatie\Activitylog\Models\Activity::forSubject($entry)->get();
    expect($logs->count())->toBeGreaterThan(0);
    expect($logs->last()->description)->toBe('Entrée vivier global');
});
