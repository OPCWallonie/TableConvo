<?php

use App\Models\Company;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

// ─────────────────────────────────────────────────────────────────────────────
// view
// ─────────────────────────────────────────────────────────────────────────────

it('user can view their own company', function () {
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);

    expect((new CompanyPolicy())->view($user, $company))->toBeTrue();
});

it('user cannot view another company', function () {
    $ownCompany   = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user         = User::factory()->create(['company_id' => $ownCompany->id]);

    expect((new CompanyPolicy())->view($user, $otherCompany))->toBeFalse();
});

it('admin can view any company', function () {
    $company = Company::factory()->create();
    $admin   = User::factory()->create();
    $admin->assignRole('admin');

    expect((new CompanyPolicy())->view($admin, $company))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// update
// ─────────────────────────────────────────────────────────────────────────────

it('user can update their own company', function () {
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);

    expect((new CompanyPolicy())->update($user, $company))->toBeTrue();
});

it('user cannot update another company', function () {
    $ownCompany   = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user         = User::factory()->create(['company_id' => $ownCompany->id]);

    expect((new CompanyPolicy())->update($user, $otherCompany))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// delete — admin seulement
// ─────────────────────────────────────────────────────────────────────────────

it('regular user cannot delete a company even their own', function () {
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);

    expect((new CompanyPolicy())->delete($user, $company))->toBeFalse();
});

it('admin can delete any company', function () {
    $company = Company::factory()->create();
    $admin   = User::factory()->create();
    $admin->assignRole('admin');

    expect((new CompanyPolicy())->delete($admin, $company))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// manageMembers — admin seulement
// ─────────────────────────────────────────────────────────────────────────────

it('regular user cannot manage members of their company', function () {
    $company = Company::factory()->create();
    $user    = User::factory()->create(['company_id' => $company->id]);

    expect((new CompanyPolicy())->manageMembers($user, $company))->toBeFalse();
});

it('admin can manage members of any company', function () {
    $company = Company::factory()->create();
    $admin   = User::factory()->create();
    $admin->assignRole('admin');

    expect((new CompanyPolicy())->manageMembers($admin, $company))->toBeTrue();
});
