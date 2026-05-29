<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

test('isCompanyAdmin retourne false si le user n\'a pas le rôle company_admin', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    expect($user->isCompanyAdmin())->toBeFalse();
    expect($user->isCompanyAdmin($company))->toBeFalse();
});

test('isCompanyAdmin retourne true si le user a le rôle et appartient à la company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    expect($user->isCompanyAdmin())->toBeTrue();
    expect($user->isCompanyAdmin($company))->toBeTrue();
});

test('isCompanyAdmin retourne false même avec le rôle si la company cible est différente', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->create(['company_id' => $ownCompany->id]);
    $user->assignRole('company_admin');

    // Appartient à ownCompany, donc ne peut pas administrer otherCompany
    expect($user->isCompanyAdmin($otherCompany))->toBeFalse();
});

test('isCompanyAdmin retourne false si le user n\'a aucune company', function () {
    $user = User::factory()->create(['company_id' => null]);
    $user->assignRole('company_admin');

    $company = Company::factory()->create();

    expect($user->isCompanyAdmin())->toBeFalse();
    expect($user->isCompanyAdmin($company))->toBeFalse();
});
