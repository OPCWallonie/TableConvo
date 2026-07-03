<?php

use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

test('view : le demandeur peut voir sa propre demande', function () {
    $company = Company::factory()->create();
    $requester = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    expect($requester->can('view', $joinRequest))->toBeTrue();
});

test('view : le company_admin de la company peut voir les demandes', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $joinRequest = CompanyJoinRequest::factory()->create(['company_id' => $company->id]);

    expect($companyAdmin->can('view', $joinRequest))->toBeTrue();
});

test('view : un super admin peut tout voir', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');
    $joinRequest = CompanyJoinRequest::factory()->create(['company_id' => $company->id]);

    expect($admin->can('view', $joinRequest))->toBeTrue();
});

test('approve/reject : company_admin de la company peut approuver/rejeter', function () {
    $company = Company::factory()->create();
    $companyAdmin = User::factory()->create(['company_id' => $company->id]);
    $companyAdmin->assignRole('company_admin');
    $joinRequest = CompanyJoinRequest::factory()->create(['company_id' => $company->id]);

    expect($companyAdmin->can('approve', $joinRequest))->toBeTrue();
    expect($companyAdmin->can('reject', $joinRequest))->toBeTrue();
});

test('approve/reject : company_admin d\'une autre company ne peut pas', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $impostor = User::factory()->create(['company_id' => $otherCompany->id]);
    $impostor->assignRole('company_admin');
    $joinRequest = CompanyJoinRequest::factory()->create(['company_id' => $company->id]);

    expect($impostor->can('approve', $joinRequest))->toBeFalse();
    expect($impostor->can('reject', $joinRequest))->toBeFalse();
});

test('cancel : seul le demandeur peut annuler sa demande', function () {
    $company = Company::factory()->create();
    $requester = User::factory()->create(['company_id' => null]);
    $other = User::factory()->create(['company_id' => null]);
    $joinRequest = CompanyJoinRequest::factory()->create([
        'company_id' => $company->id,
        'user_id'    => $requester->id,
    ]);

    expect($requester->can('cancel', $joinRequest))->toBeTrue();
    expect($other->can('cancel', $joinRequest))->toBeFalse();
});
