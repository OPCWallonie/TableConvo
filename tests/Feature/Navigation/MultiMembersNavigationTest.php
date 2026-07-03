<?php

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',         'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

it('shows Panel admin link and hides member links for super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('espace.profil'));

    $response->assertSee('Panel admin');
    $response->assertDontSee('Tableau de bord');
    $response->assertDontSee('Mes inscriptions');
    $response->assertDontSee('Mes cartes');
});

it('shows member links and hides Panel admin for regular member', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create();

    $response = $this->actingAs($member)->get(route('espace.profil'));

    $response->assertSee('Tableau de bord');
    $response->assertSee('Mes inscriptions');
    $response->assertSee('Mes cartes');
    $response->assertDontSee('Panel admin');
});

it('shows Mes membres link for company_admin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->for($company)->create();
    $admin->assignRole('company_admin');

    $response = $this->actingAs($admin)->get(route('espace.profil'));

    $response->assertSee('Mes membres');
});

it('hides Mes membres link for member without company_admin role', function () {
    $company = Company::factory()->create();
    $member = User::factory()->for($company)->create();

    $response = $this->actingAs($member)->get(route('espace.profil'));

    $response->assertDontSee('Mes membres');
});
