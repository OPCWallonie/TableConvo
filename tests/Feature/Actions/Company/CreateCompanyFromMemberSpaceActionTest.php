<?php

use App\Actions\Company\CreateCompanyFromMemberSpaceAction;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

test('crée une company et assigne company_admin au user', function () {
    $user = User::factory()->create(['company_id' => null, 'email' => 'arnaud@acme-sa.be']);

    $action = app(CreateCompanyFromMemberSpaceAction::class);
    $company = $action->execute($user, [
        'company_name' => 'Acme SA',
        'vat_number'   => 'BE0123456789',
        'street'       => 'Rue de la Paix 1',
        'postal_code'  => '1000',
        'city'         => 'Bruxelles',
    ]);

    expect($company)->toBeInstanceOf(Company::class);
    expect($user->fresh()->company_id)->toBe($company->id);
    expect($user->fresh()->hasRole('company_admin'))->toBeTrue();
});

test('stocke le domaine email pro dans email_domain', function () {
    $user = User::factory()->create(['company_id' => null, 'email' => 'arnaud@acme-sa.be']);

    $company = app(CreateCompanyFromMemberSpaceAction::class)->execute($user, [
        'company_name' => 'Acme SA',
        'vat_number'   => 'BE0123456789',
        'street'       => 'Rue 1',
        'postal_code'  => '1000',
        'city'         => 'BXL',
    ]);

    expect($company->email_domain)->toBe('acme-sa.be');
});

test('stocke null pour email_domain si domaine générique', function () {
    $user = User::factory()->create(['company_id' => null, 'email' => 'arnaud@gmail.com']);

    $company = app(CreateCompanyFromMemberSpaceAction::class)->execute($user, [
        'company_name' => 'Acme SA',
        'vat_number'   => 'BE0123456789',
        'street'       => 'Rue 1',
        'postal_code'  => '1000',
        'city'         => 'BXL',
    ]);

    expect($company->email_domain)->toBeNull();
});

test('lève RuntimeException company_exists si TVA déjà enregistrée', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789']);
    $user = User::factory()->create(['company_id' => null, 'email' => 'arnaud@acme-sa.be']);

    expect(fn () => app(CreateCompanyFromMemberSpaceAction::class)->execute($user, [
        'company_name' => 'Acme SA',
        'vat_number'   => 'BE0123456789',
        'street'       => 'Rue 1',
        'postal_code'  => '1000',
        'city'         => 'BXL',
    ]))->toThrow(\RuntimeException::class, 'company_exists');
});

test('abort 403 si le user est admin TableConvo', function () {
    $admin = User::factory()->create(['company_id' => null]);
    $admin->assignRole('admin');

    expect(fn () => app(CreateCompanyFromMemberSpaceAction::class)->execute($admin, [
        'company_name' => 'Acme SA',
        'vat_number'   => 'BE0123456789',
    ]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('abort 409 si le user est déjà rattaché à une company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    expect(fn () => app(CreateCompanyFromMemberSpaceAction::class)->execute($user, [
        'company_name' => 'Autre SA',
        'vat_number'   => 'BE0987654321',
    ]))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
