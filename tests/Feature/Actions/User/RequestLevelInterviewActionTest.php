<?php

use App\Actions\User\RequestLevelInterviewAction;
use App\Models\User;
use App\Notifications\NotifyAdminOfLevelInterviewNeeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('sets interview_requested_at and notifies all admins', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin1 = User::factory()->create();
    $admin2 = User::factory()->create();
    $admin1->assignRole('admin');
    $admin2->assignRole('admin');

    $user = User::factory()->create(['interview_requested_at' => null]);

    app(RequestLevelInterviewAction::class)->execute($user);

    expect($user->fresh()->interview_requested_at)->not->toBeNull();
    Notification::assertSentTo($admin1, NotifyAdminOfLevelInterviewNeeded::class);
    Notification::assertSentTo($admin2, NotifyAdminOfLevelInterviewNeeded::class);
});

it('is idempotent: second call does not send another notification', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create(['interview_requested_at' => null]);
    $action = app(RequestLevelInterviewAction::class);

    $action->execute($user);
    $action->execute($user);

    Notification::assertCount(1);
});

it('does nothing when interview_requested_at is already set', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create(['interview_requested_at' => now()->subDays(2)]);

    app(RequestLevelInterviewAction::class)->execute($user);

    Notification::assertNothingSent();
});

it('writes an activity log entry on the user subject', function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['interview_requested_at' => null]);

    app(RequestLevelInterviewAction::class)->execute($user);

    $log = Activity::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($user->id);
});
