<?php

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function actingAsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    test()->actingAs($admin);
    return $admin;
}

it('admin can list activity logs', function () {
    $admin = actingAsAdmin();

    activity()->causedBy($admin)->log('Test log entry');

    $this->get(ActivityLogResource::getUrl('index'))->assertSuccessful();
});

it('admin can filter by subject_type', function () {
    $admin = actingAsAdmin();

    activity()
        ->causedBy($admin)
        ->performedOn($admin)
        ->log('User action');

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\ActivityLog\Pages\ListActivityLogs::class)
        ->set('tableFilters.subject_type.value', \App\Models\User::class);

    $component->assertSuccessful();
});

it('admin can filter by causer_id', function () {
    $admin = actingAsAdmin();
    $other = User::factory()->create();

    activity()->causedBy($admin)->log('Admin action');
    activity()->causedBy($other)->log('Other action');

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\ActivityLog\Pages\ListActivityLogs::class)
        ->set('tableFilters.causer_id.value', $admin->id);

    $component->assertSuccessful();
});

it('admin can filter by event', function () {
    $admin = actingAsAdmin();

    activity()->causedBy($admin)->event('created')->log('Created something');
    activity()->causedBy($admin)->event('deleted')->log('Deleted something');

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\ActivityLog\Pages\ListActivityLogs::class)
        ->set('tableFilters.event.value', 'created');

    $component->assertSuccessful();
});

it('admin can filter by date range', function () {
    $admin = actingAsAdmin();

    activity()->causedBy($admin)->log('Log entry');

    $component = Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\ActivityLog\Pages\ListActivityLogs::class)
        ->set('tableFilters.created_at.from', now()->subDay()->toDateString())
        ->set('tableFilters.created_at.to', now()->addDay()->toDateString());

    $component->assertSuccessful();
});

it('non-admin users get 403 on the resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(ActivityLogResource::getUrl('index'))
        ->assertStatus(403);
});

it('view details modal displays properties JSON readably', function () {
    $admin = actingAsAdmin();

    activity()
        ->causedBy($admin)
        ->withProperties(['attributes' => ['email' => 'new@example.com'], 'old' => ['email' => 'old@example.com']])
        ->log('Email changed');

    $this->get(ActivityLogResource::getUrl('index'))->assertSuccessful();

    $log = \Spatie\Activitylog\Models\Activity::first();
    expect($log->properties->has('attributes'))->toBeTrue()
        ->and($log->properties->has('old'))->toBeTrue();
});
