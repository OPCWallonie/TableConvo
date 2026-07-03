<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'company_admin', 'guard_name' => 'web']);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    mockVat();

    $response = $this->post('/register', registrationPayload());

    $this->assertAuthenticated();
    $response->assertRedirect(route('espace.dashboard', absolute: false));
});

// Comportement Phase 9.6 : TVA déjà prise = cas 3 (join request), plus un rejet.
// Couverture détaillée dans CompanyHijackingTest.php.
test('registering with existing VAT creates user and join request instead of rejecting', function () {
    Company::factory()->create(['vat_number' => 'BE0123456789', 'email_domain' => null]);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'newcomer@example.com']));

    $this->assertAuthenticated();
    $response->assertRedirect(route('espace.profil', absolute: false));
    $response->assertSessionHas('status', 'request_pending');
});
