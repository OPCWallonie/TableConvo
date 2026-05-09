<?php

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────────────────────────────────────
// view
// ─────────────────────────────────────────────────────────────────────────────

it('user can view their own profile', function () {
    $user = User::factory()->create();

    expect((new UserPolicy())->view($user, $user))->toBeTrue();
});

it('user cannot view another user profile', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserPolicy())->view($user, $other))->toBeFalse();
});

it('admin can view any user profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $other = User::factory()->create();

    expect((new UserPolicy())->view($admin, $other))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// update
// ─────────────────────────────────────────────────────────────────────────────

it('user can update their own profile', function () {
    $user = User::factory()->create();

    expect((new UserPolicy())->update($user, $user))->toBeTrue();
});

it('user cannot update another user profile', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserPolicy())->update($user, $other))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// delete
// ─────────────────────────────────────────────────────────────────────────────

it('user can delete their own account', function () {
    $user = User::factory()->create();

    expect((new UserPolicy())->delete($user, $user))->toBeTrue();
});

it('user cannot delete another user account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserPolicy())->delete($user, $other))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// anonymize
// ─────────────────────────────────────────────────────────────────────────────

it('user can anonymize their own account', function () {
    $user = User::factory()->create();

    expect((new UserPolicy())->anonymize($user, $user))->toBeTrue();
});

it('user cannot anonymize another user account', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    expect((new UserPolicy())->anonymize($user, $other))->toBeFalse();
});

it('admin can anonymize any user account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $other = User::factory()->create();

    expect((new UserPolicy())->anonymize($admin, $other))->toBeTrue();
});
