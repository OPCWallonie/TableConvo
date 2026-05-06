<?php

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Services\Mollie\MollieService;
use App\Settings\MollieSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(MollieSettings::class);
    $settings->api_key = '';
    $settings->save();
});

it('is in stub mode when api_key is empty', function () {
    expect(app(MollieService::class)->isStubMode())->toBeTrue();
});

it('is not in stub mode when api_key is set', function () {
    $settings = app(MollieSettings::class);
    $settings->api_key = 'test_abc123';
    $settings->save();

    expect(app(MollieService::class)->isStubMode())->toBeFalse();
});

it('createPayment returns stub payment_id and local url in stub mode', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
        'total_ttc' => 250.00,
    ]);

    $result = app(MollieService::class)->createPayment($order);

    expect($result['payment_id'])->toStartWith('stub_');
    expect($result['checkout_url'])->toContain('paiement/stub/');
});

it('fetchPayment returns paid for stub_ payment ids', function () {
    $result = app(MollieService::class)->fetchPayment('stub_abc123xyz');

    expect($result['status'])->toBe('paid');
    expect($result['paid_at'])->not->toBeNull();
});

it('fetchPayment returns paid in stub mode regardless of id', function () {
    $result = app(MollieService::class)->fetchPayment('any_id_in_stub');

    expect($result['status'])->toBe('paid');
});
