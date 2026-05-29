<?php

use App\Services\Vat\VatLookupResult;
use App\Services\Vat\VatValidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->service = new VatValidationService();
});

test('lookup retourne null si VIES KO', function () {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $result = $this->service->lookup('BE0123456789');

    expect($result)->toBeNull();
});

test('lookup retourne null si VIES lève une exception', function () {
    Http::fake([
        '*' => function () {
            throw new \RuntimeException('Connection refused');
        },
    ]);

    $result = $this->service->lookup('BE0123456789');

    expect($result)->toBeNull();
});

test('lookup retourne un VatLookupResult populé si VIES OK', function () {
    Http::fake([
        '*' => Http::response([
            'isValid'  => true,
            'name'     => 'ACME SA',
            'address'  => 'Rue de la Loi 1, 1000 Bruxelles',
            'vatNumber' => '0123456789',
        ]),
    ]);

    $result = $this->service->lookup('BE0123456789');

    expect($result)->toBeInstanceOf(VatLookupResult::class);
    expect($result->name)->toBe('ACME SA');
    expect($result->address)->toBe('Rue de la Loi 1, 1000 Bruxelles');
    expect($result->vatNumber)->toBe('BE0123456789');
    expect($result->validatedAt)->not->toBeNull();
    expect($result->nameIsUndisclosed())->toBeFalse();
    expect($result->addressIsUndisclosed())->toBeFalse();
});

test('lookup gère le cas name === "---" (entreprise qui refuse la publication)', function () {
    Http::fake([
        '*' => Http::response([
            'isValid' => true,
            'name'    => '---',
            'address' => '---',
        ]),
    ]);

    $result = $this->service->lookup('BE0123456789');

    expect($result)->toBeInstanceOf(VatLookupResult::class);
    expect($result->nameIsUndisclosed())->toBeTrue();
    expect($result->addressIsUndisclosed())->toBeTrue();
});

test('lookup retourne null si isValid est false', function () {
    Http::fake([
        '*' => Http::response([
            'isValid' => false,
            'name'    => '',
            'address' => '',
        ]),
    ]);

    $result = $this->service->lookup('BE0123456789');

    expect($result)->toBeNull();
});
