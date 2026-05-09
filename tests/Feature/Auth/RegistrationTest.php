<?php

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

test('registration rejected when company vat already exists (anti-hijacking)', function () {
    // Pre-create a company with this TVA number
    Company::factory()->create(['vat_number' => 'BE0123456789']);

    mockVat();

    $response = $this->post('/register', registrationPayload(['email' => 'attacker@example.com']));

    $this->assertGuest();
    $response->assertSessionHasErrors('vat_number');
    $this->assertStringContainsString(
        'déjà enregistrée',
        session('errors')->first('vat_number')
    );
});
