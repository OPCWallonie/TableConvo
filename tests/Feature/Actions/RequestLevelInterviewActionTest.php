<?php

use App\Actions\User\RequestLevelInterviewAction;
use App\Models\User;
use App\Notifications\NotifyAdminOfLevelInterviewNeeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeInterviewSetup(): array
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user  = User::factory()->create(['interview_requested_at' => null]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return compact('user', 'admin');
}

// ─── Tests ──────────────────────────────────────────────────

it('creates an interview request by setting interview_requested_at', function () {
    ['user' => $user] = makeInterviewSetup();

    Notification::fake();
    app(RequestLevelInterviewAction::class)->execute($user);

    expect($user->fresh()->interview_requested_at)->not->toBeNull();
});

it('is idempotent — does not resend notification if request already exists', function () {
    ['user' => $user, 'admin' => $admin] = makeInterviewSetup();

    Notification::fake();

    app(RequestLevelInterviewAction::class)->execute($user);
    $firstRequestedAt = $user->fresh()->interview_requested_at;

    app(RequestLevelInterviewAction::class)->execute($user);

    // interview_requested_at must not change on second call
    expect($user->fresh()->interview_requested_at->eq($firstRequestedAt))->toBeTrue();

    // Admin notified exactly once
    Notification::assertSentToTimes($admin, NotifyAdminOfLevelInterviewNeeded::class, 1);
});

it('notifies admin users with NotifyAdminOfLevelInterviewNeeded', function () {
    ['user' => $user, 'admin' => $admin] = makeInterviewSetup();

    Notification::fake();
    app(RequestLevelInterviewAction::class)->execute($user);

    Notification::assertSentTo($admin, NotifyAdminOfLevelInterviewNeeded::class, function ($notification) use ($user) {
        return $notification->applicant->id === $user->id;
    });
});

it('logs activity caused by the requesting user', function () {
    ['user' => $user] = makeInterviewSetup();

    Notification::fake();
    app(RequestLevelInterviewAction::class)->execute($user);

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toContain('entretien');
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->subject_id)->toBe($user->id);
});
