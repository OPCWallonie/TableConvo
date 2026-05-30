<?php

use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\RelationManagers\CompanyMembersRelationManager;
use App\Filament\Resources\Companies\RelationManagers\PendingJoinRequestsRelationManager;
use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

function makeCompanyResourceAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('CompanyMembersRelationManager renders members of the company', function () {
    $admin   = makeCompanyResourceAdmin();
    $company = Company::factory()->create();
    $member  = User::factory()->for($company)->create();

    Livewire::actingAs($admin)
        ->test(CompanyMembersRelationManager::class, [
            'ownerRecord' => $company,
            'pageClass'   => EditCompany::class,
        ])
        ->assertSuccessful()
        ->assertSee($member->email);
});

it('PendingJoinRequestsRelationManager renders pending join requests', function () {
    $admin   = makeCompanyResourceAdmin();
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => null]);

    CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $user->id,
    ]);

    Livewire::actingAs($admin)
        ->test(PendingJoinRequestsRelationManager::class, [
            'ownerRecord' => $company,
            'pageClass'   => EditCompany::class,
        ])
        ->assertSuccessful()
        ->assertSee($user->email);
});

it('reassignAdmin action is visible for super admin on EditCompany', function () {
    $admin   = makeCompanyResourceAdmin();
    $company = Company::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->assertActionVisible('reassignAdmin');
});

it('non-admin cannot access CompanyResource', function () {
    $company = Company::factory()->create();
    $member  = User::factory()->for($company)->create();

    $this->actingAs($member)
        ->get(\App\Filament\Resources\Companies\CompanyResource::getUrl('index'))
        ->assertForbidden();
});
