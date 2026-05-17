<?php

use App\Actions\GlobalWaitlist\DismissGlobalWaitlistEntryAction;
use App\Enums\GlobalWaitlistEntryStatus;
use App\Models\GlobalWaitlistEntry;
use App\Models\User;
use App\Notifications\DismissedFromGlobalPoolNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeDismissSetup(): array
{
    $admin = User::factory()->create();
    $user  = User::factory()->create();
    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'user', 'entry');
}

// ─── Tests ──────────────────────────────────────────────────

it('admin can dismiss a pending entry with reason', function () {
    ['admin' => $admin, 'entry' => $entry] = makeDismissSetup();

    $result = app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $admin, 'Plus de place pour ce niveau.', false
    );

    expect($result->status)->toBe(GlobalWaitlistEntryStatus::Dismissed);
    expect($result->dismissed_reason)->toBe('Plus de place pour ce niveau.');
    expect($result->dismissed_at)->not->toBeNull();
    expect($result->dismissed_by)->toBe($admin->id);
});

it('user can dismiss their own pending entry', function () {
    ['user' => $user, 'entry' => $entry] = makeDismissSetup();

    $result = app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $user, 'Je me retire.', true
    );

    expect($result->status)->toBe(GlobalWaitlistEntryStatus::Dismissed);
    expect($result->dismissed_by)->toBe($user->id);
});

it('user cannot dismiss another user\'s entry (unauthorized_dismiss)', function () {
    ['entry' => $entry] = makeDismissSetup();

    $otherUser = User::factory()->create();

    expect(fn () => app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $otherUser, 'Je me retire.', true
    ))->toThrow(RuntimeException::class, 'unauthorized_dismiss');
});

it('requires dismiss_reason', function () {
    ['admin' => $admin, 'entry' => $entry] = makeDismissSetup();

    expect(fn () => app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $admin, '   ', false
    ))->toThrow(RuntimeException::class, 'dismiss_reason_required');

    expect(fn () => app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $admin, '', false
    ))->toThrow(RuntimeException::class, 'dismiss_reason_required');
});

it('throws entry_not_pending', function () {
    ['admin' => $admin] = makeDismissSetup();

    $dismissedEntry = GlobalWaitlistEntry::factory()->dismissed()->create([
        'created_by' => $admin->id,
    ]);

    expect(fn () => app(DismissGlobalWaitlistEntryAction::class)->execute(
        $dismissedEntry, $admin, 'Raison quelconque.', false
    ))->toThrow(RuntimeException::class, 'entry_not_pending');
});

it('dispatches DismissedFromGlobalPoolNotification when admin-triggered', function () {
    Notification::fake();

    ['admin' => $admin, 'user' => $user, 'entry' => $entry] = makeDismissSetup();

    app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $admin, 'Retrait admin.', false
    );

    Notification::assertSentTo($user, DismissedFromGlobalPoolNotification::class);
});

it('does not dispatch DismissedFromGlobalPoolNotification when user-triggered', function () {
    Notification::fake();

    ['admin' => $admin, 'user' => $user, 'entry' => $entry] = makeDismissSetup();

    app(DismissGlobalWaitlistEntryAction::class)->execute(
        $entry, $user, 'Je me retire.', true
    );

    Notification::assertNotSentTo($user, DismissedFromGlobalPoolNotification::class);
});
