<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

it('redirects guest to login', function () {
    $this->get(route('espace.societe.creer'))
        ->assertRedirect(route('login'));
});

it('redirects admin to filament dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('espace.societe.creer'))
        ->assertRedirect(route('filament.admin.pages.dashboard'));
});

it('redirects user already attached to a company to profil', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $this->actingAs($user)
        ->get(route('espace.societe.creer'))
        ->assertRedirect(route('espace.profil'));
});

it('shows company creation form to unattached member', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->get(route('espace.societe.creer'))
        ->assertOk()
        ->assertViewIs('espace.societe.creer');
});

it('store creates the company and redirects with company_created status', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->post(route('espace.societe.store'), [
            'vat_number'   => 'BE0123456789',
            'company_name' => 'Test SA',
            'street'       => 'Rue de la Loi 1',
            'postal_code'  => '1000',
            'city'         => 'Bruxelles',
        ])
        ->assertRedirect(route('espace.profil'))
        ->assertSessionHas('status', 'company_created');

    expect(Company::where('vat_number', 'BE0123456789')->exists())->toBeTrue();
    expect($user->fresh()->company_id)->not->toBeNull();
});

it('store redirects to rejoindre with company_exists status when VAT is already registered', function () {
    $existing = Company::factory()->create(['vat_number' => 'BE0123456789']);
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->post(route('espace.societe.store'), [
            'vat_number'   => 'BE0123456789',
            'company_name' => 'Test SA',
            'street'       => 'Rue de la Loi 1',
            'postal_code'  => '1000',
            'city'         => 'Bruxelles',
        ])
        ->assertRedirect(route('espace.societe.rejoindre', ['vat' => 'BE0123456789']))
        ->assertSessionHas('status', 'company_exists');
});

it('store returns validation errors for missing required fields', function () {
    $user = User::factory()->create(['company_id' => null]);

    $this->actingAs($user)
        ->post(route('espace.societe.store'), [])
        ->assertSessionHasErrors(['vat_number', 'company_name', 'street', 'postal_code', 'city']);
});

it('store returns 403 for admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('espace.societe.store'), [
            'vat_number'   => 'BE0987654321',
            'company_name' => 'Test SA',
            'street'       => 'Rue Test 1',
            'postal_code'  => '1000',
            'city'         => 'Bruxelles',
        ])
        ->assertForbidden();
});
