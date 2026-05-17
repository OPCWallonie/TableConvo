<?php

use App\Actions\User\AssignLevelAction;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeAssignLevelSetup(): array
{
    $admin = User::factory()->create();
    $user  = User::factory()->create(['level_id' => null]);
    $level = Level::factory()->create(['code' => 'B1', 'sort_order' => 3]);

    return compact('admin', 'user', 'level');
}

// ─── Tests ──────────────────────────────────────────────────

it('assigns level to user and sets level_assigned_at', function () {
    ['admin' => $admin, 'user' => $user, 'level' => $level] = makeAssignLevelSetup();

    $result = app(AssignLevelAction::class)->execute($user, $level, $admin);

    expect($result->level_id)->toBe($level->id);
    expect($result->level_assigned_at)->not->toBeNull();
});

it('logs activity with level code in properties', function () {
    ['admin' => $admin, 'user' => $user, 'level' => $level] = makeAssignLevelSetup();

    app(AssignLevelAction::class)->execute($user, $level, $admin);

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toContain($level->code);
    expect($activity->properties['level_code'])->toBe($level->code);
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->subject_id)->toBe($user->id);
});

it('can reassign the same level without error and updates level_assigned_at', function () {
    ['admin' => $admin, 'user' => $user, 'level' => $level] = makeAssignLevelSetup();

    app(AssignLevelAction::class)->execute($user, $level, $admin);
    $firstAssignedAt = $user->fresh()->level_assigned_at;

    // Small delay to ensure timestamp differs
    sleep(1);

    app(AssignLevelAction::class)->execute($user, $level, $admin);
    $secondAssignedAt = $user->fresh()->level_assigned_at;

    expect($user->fresh()->level_id)->toBe($level->id);
    expect($secondAssignedAt->gte($firstAssignedAt))->toBeTrue();
});

it('overwrites a previous level assignment with the new one', function () {
    ['admin' => $admin, 'user' => $user] = makeAssignLevelSetup();

    $levelA = Level::factory()->create(['code' => 'A1', 'sort_order' => 1]);
    $levelB = Level::factory()->create(['code' => 'C1', 'sort_order' => 5]);

    app(AssignLevelAction::class)->execute($user, $levelA, $admin);
    expect($user->fresh()->level_id)->toBe($levelA->id);

    app(AssignLevelAction::class)->execute($user, $levelB, $admin);
    expect($user->fresh()->level_id)->toBe($levelB->id);
});
