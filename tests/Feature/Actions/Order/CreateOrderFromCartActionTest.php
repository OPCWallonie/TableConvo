<?php

use App\Actions\Order\CreateOrderFromCartAction;
use App\Enums\OrderStatus;
use App\Models\CardType;
use App\Models\Company;
use App\Models\User;
use App\Services\Mollie\MollieService;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(InvoicingSettings::class);
    $settings->default_vat_rate = 21.00;
    $settings->save();
});

function makeUserWithCompany(): User
{
    $company = Company::factory()->create([
        'name' => 'Acme SA',
        'vat_number' => 'BE0123456789',
        'street' => 'Rue Test 1',
        'postal_code' => '4000',
        'city' => 'Liège',
        'country' => 'Belgique',
    ]);
    return User::factory()->create(['company_id' => $company->id]);
}

it('creates an order with pending status', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00, 'sessions_count' => 10, 'validity_months' => 12]);

    $result = app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]);

    expect($result['order']->status)->toBe(OrderStatus::Pending);
    expect($result['order']->user_id)->toBe($user->id);
});

it('creates order items with correct HT and TVA amounts', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00]);

    $result = app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 2]);

    $order = $result['order'];
    $item = $order->items->first();

    $expectedHt = round(250.00 / 1.21, 2, PHP_ROUND_HALF_UP);
    expect((float) $item->unit_price_ht)->toBe($expectedHt);
    expect($item->quantity)->toBe(2);
    expect((float) $item->vat_rate)->toBe(21.00);
    expect((float) $order->total_ttc)->toBe(500.00);
});

it('copies the company snapshot correctly', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00]);

    $result = app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]);

    $snapshot = $result['order']->company_snapshot;
    expect($snapshot['name'])->toBe('Acme SA');
    expect($snapshot['vat_number'])->toBe('BE0123456789');
    expect($snapshot['city'])->toBe('Liège');
});

it('does not create cards at order creation', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00]);

    app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]);

    expect($user->cards()->count())->toBe(0);
});

it('clears the session cart after creating order', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00]);
    session(['cart.items' => [$cardType->id => 1]]);

    app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]);

    expect(session('cart.items'))->toBeNull();
});

it('returns a stub checkout url when api_key is empty', function () {
    $user = makeUserWithCompany();
    $cardType = CardType::factory()->create(['price' => 250.00]);

    $result = app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]);

    expect($result['checkout_url'])->toContain('paiement/stub/');
});

it('throws when user has no company', function () {
    $user = User::factory()->create(['company_id' => null]);
    $cardType = CardType::factory()->create(['price' => 250.00]);

    expect(fn () => app(CreateOrderFromCartAction::class)->execute($user, [$cardType->id => 1]))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('throws when cart is empty', function () {
    $user = makeUserWithCompany();

    expect(fn () => app(CreateOrderFromCartAction::class)->execute($user, []))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
