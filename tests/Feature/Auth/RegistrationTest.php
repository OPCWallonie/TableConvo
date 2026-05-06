<?php

use App\Models\Company;
use App\Services\Vat\VatValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mockVat(string $normalized = 'BE0123456789'): void
{
    test()->mock(VatValidationService::class, function ($mock) use ($normalized) {
        $mock->shouldReceive('normalize')->andReturn($normalized);
        $mock->shouldReceive('isFormatValid')->andReturn(true);
        $mock->shouldReceive('validate')->andReturn(true);
    });
}

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name'            => 'Test',
        'last_name'             => 'User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'company_name'          => 'Acme SA',
        'vat_number'            => 'BE0123456789',
        'street'                => 'Rue de la Paix 1',
        'postal_code'           => '1000',
        'city'                  => 'Bruxelles',
    ], $overrides);
}

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
