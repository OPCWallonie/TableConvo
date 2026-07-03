<?php

use App\Actions\Company\AssignCompanyAdminAction;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

function makeFilamentAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('setAsCompanyAdmin action is visible for super admin on user with company', function () {
    $superAdmin = makeFilamentAdmin();
    $company    = Company::factory()->create();
    $member     = User::factory()->for($company)->create();

    Livewire::actingAs($superAdmin)
        ->test(ListUsers::class)
        ->assertTableActionVisible('setAsCompanyAdmin', record: $member);
});

it('setAsCompanyAdmin action is not visible for user without company', function () {
    $superAdmin = makeFilamentAdmin();
    $member     = User::factory()->create(['company_id' => null]);

    Livewire::actingAs($superAdmin)
        ->test(ListUsers::class)
        ->assertTableActionHidden('setAsCompanyAdmin', record: $member);
});

it('setAsCompanyAdmin action calls AssignCompanyAdminAction and reassigns role', function () {
    $superAdmin = makeFilamentAdmin();
    $company    = Company::factory()->create();

    $oldAdmin = User::factory()->for($company)->create();
    $oldAdmin->assignRole('company_admin');

    $newAdmin = User::factory()->for($company)->create();

    Livewire::actingAs($superAdmin)
        ->test(ListUsers::class)
        ->callTableAction('setAsCompanyAdmin', record: $newAdmin);

    expect($newAdmin->fresh()->hasRole('company_admin'))->toBeTrue();
    expect($oldAdmin->fresh()->hasRole('company_admin'))->toBeFalse();
});

it('setAsCompanyAdmin produces an activity log entry', function () {
    $superAdmin = makeFilamentAdmin();
    $company    = Company::factory()->create();
    $member     = User::factory()->for($company)->create();

    Livewire::actingAs($superAdmin)
        ->test(ListUsers::class)
        ->callTableAction('setAsCompanyAdmin', record: $member);

    $activity = Activity::where('description', 'Réassignation company_admin forcée par super admin')->first();
    expect($activity)->not->toBeNull();
});

it('non-admin cannot access UserResource', function () {
    $company = Company::factory()->create();
    $member  = User::factory()->for($company)->create();

    $this->actingAs($member)
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});
