<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => \Illuminate\Support\Facades\Cache::flush())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function mockVat(string $normalized = 'BE0123456789'): void
{
    test()->mock(\App\Services\Vat\VatValidationService::class, function ($mock) use ($normalized) {
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
