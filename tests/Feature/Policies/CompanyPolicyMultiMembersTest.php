<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

// --- manageMembers ---

test('manageMembers : company_admin de la company peut gérer ses membres', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    expect($user->can('manageMembers', $company))->toBeTrue();
});

test('manageMembers : company_admin d\'une autre company ne peut pas', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $otherCompany->id]);
    $user->assignRole('company_admin');

    expect($user->can('manageMembers', $company))->toBeFalse();
});

test('manageMembers : super admin peut toujours gérer les membres', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    expect($admin->can('manageMembers', $company))->toBeTrue();
});

test('manageMembers : simple member ne peut pas gérer les membres', function () {
    $company = Company::factory()->create();
    $member = User::factory()->create(['company_id' => $company->id]);

    expect($member->can('manageMembers', $company))->toBeFalse();
});

// --- reassignAdmin ---

test('reassignAdmin : seul le super admin peut réassigner', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    expect($admin->can('reassignAdmin', $company))->toBeTrue();
});

test('reassignAdmin : company_admin ne peut pas réassigner', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');

    expect($companyAdmin->can('reassignAdmin', $company))->toBeFalse();
});

test('reassignAdmin : simple member ne peut pas réassigner', function () {
    $company = Company::factory()->create();
    $member = User::factory()->create(['company_id' => $company->id]);

    expect($member->can('reassignAdmin', $company))->toBeFalse();
});

test('reassignAdmin : company_admin d\'une autre company ne peut pas réassigner', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $impostor = User::factory()->create(['company_id' => $otherCompany->id]);
    $impostor->assignRole('company_admin');

    expect($impostor->can('reassignAdmin', $company))->toBeFalse();
});
