<?php

use App\Actions\User\AssignLevelAction;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('assigns a level to the user and records the timestamp', function () {
    $level = Level::factory()->create(['code' => 'B2']);
    $user  = User::factory()->create(['level_id' => null, 'level_assigned_at' => null]);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $result = app(AssignLevelAction::class)->execute($user, $level, $admin);

    expect($result->level_id)->toBe($level->id);
    expect($result->level_assigned_at)->not->toBeNull();
});

it('replaces an existing level with the new one', function () {
    $levelA = Level::factory()->create(['code' => 'A1']);
    $levelB = Level::factory()->create(['code' => 'B1']);
    $user   = User::factory()->withLevel($levelA)->create();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $result = app(AssignLevelAction::class)->execute($user, $levelB, $admin);

    expect($result->level_id)->toBe($levelB->id);
    expect($result->level->code)->toBe('B1');
});

it('writes an activity log entry with the level code and admin as causer', function () {
    $level = Level::factory()->create(['code' => 'C2']);
    $user  = User::factory()->create(['level_id' => null]);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    app(AssignLevelAction::class)->execute($user, $level, $admin);

    $log = Activity::where('subject_id', $user->id)
        ->where('subject_type', User::class)
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($admin->id);
    expect($log->properties['level_code'])->toBe('C2');
});
