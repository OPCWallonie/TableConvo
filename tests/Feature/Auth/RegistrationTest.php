<?php

use App\Services\Vat\VatValidationService;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $this->mock(VatValidationService::class, function ($mock) {
        $mock->shouldReceive('normalize')->andReturn('BE0123456789');
        $mock->shouldReceive('isFormatValid')->andReturn(true);
        $mock->shouldReceive('validate')->andReturn(true);
    });

    $response = $this->post('/register', [
        'first_name'    => 'Test',
        'last_name'     => 'User',
        'email'         => 'test@example.com',
        'password'      => 'password',
        'password_confirmation' => 'password',
        'company_name'  => 'Acme SA',
        'vat_number'    => 'BE0123456789',
        'street'        => 'Rue de la Paix 1',
        'postal_code'   => '1000',
        'city'          => 'Bruxelles',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('espace.dashboard', absolute: false));
});
