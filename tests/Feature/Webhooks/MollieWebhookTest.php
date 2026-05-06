<?php

use App\Enums\OrderStatus;
use App\Jobs\SendInvoiceByEmailJob;
use App\Models\CardType;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Mollie\MollieService;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(InvoicingSettings::class);
    $settings->default_vat_rate = 21.00;
    $settings->save();
    Queue::fake();
});

function makePendingOrderForWebhook(string $mollieId = 'stub_test123'): Order
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $cardType = CardType::factory()->create(['sessions_count' => 10, 'validity_months' => 12, 'price' => 250.00]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
        'mollie_payment_id' => $mollieId,
        'company_snapshot' => [
            'name' => $company->name,
            'vat_number' => $company->vat_number ?? 'BE0000000000',
            'street' => $company->street ?? '',
            'postal_code' => $company->postal_code ?? '',
            'city' => $company->city ?? '',
            'country' => 'Belgique',
        ],
        'total_ht' => 206.61,
        'total_vat' => 43.39,
        'total_ttc' => 250.00,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'card_type_id' => $cardType->id,
        'quantity' => 1,
        'unit_price_ht' => 206.61,
        'vat_rate' => 21.00,
        'vat_amount' => 43.39,
        'total_ht' => 206.61,
        'total_ttc' => 250.00,
    ]);

    return $order;
}

it('marks order as paid and creates cards when webhook received for pending order', function () {
    $order = makePendingOrderForWebhook('stub_abc123');

    $response = $this->post(route('webhooks.mollie'), ['id' => 'stub_abc123']);

    $response->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect($order->cards()->count())->toBe(1);
});

it('is idempotent — second webhook does not create duplicate cards or invoices', function () {
    $order = makePendingOrderForWebhook('stub_idempotent');

    $this->post(route('webhooks.mollie'), ['id' => 'stub_idempotent']);
    $this->post(route('webhooks.mollie'), ['id' => 'stub_idempotent']);

    expect($order->cards()->count())->toBe(1);
    expect($order->invoice()->count())->toBe(1);
});

it('returns 404 for unknown mollie payment id', function () {
    $this->post(route('webhooks.mollie'), ['id' => 'tr_unknown_xyz'])
        ->assertNotFound();
});

it('marks order as failed when payment status is failed', function () {
    $order = makePendingOrderForWebhook('tr_failed_real');

    $mollieService = Mockery::mock(MollieService::class);
    $mollieService->shouldReceive('isStubMode')->andReturn(false);
    $mollieService->shouldReceive('fetchPayment')->with('tr_failed_real')->andReturn([
        'status' => 'failed',
        'paid_at' => null,
    ]);
    $this->app->instance(MollieService::class, $mollieService);

    $this->post(route('webhooks.mollie'), ['id' => 'tr_failed_real'])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Failed);
    expect($order->cards()->count())->toBe(0);
});

it('does not create cards for failed payment', function () {
    $order = makePendingOrderForWebhook('tr_failed_cards');

    $mollieService = Mockery::mock(MollieService::class);
    $mollieService->shouldReceive('isStubMode')->andReturn(false);
    $mollieService->shouldReceive('fetchPayment')->with('tr_failed_cards')->andReturn([
        'status' => 'canceled',
        'paid_at' => null,
    ]);
    $this->app->instance(MollieService::class, $mollieService);

    $this->post(route('webhooks.mollie'), ['id' => 'tr_failed_cards']);

    expect($order->cards()->count())->toBe(0);
});

it('returns 200 when no id in request', function () {
    $this->post(route('webhooks.mollie'), [])
        ->assertOk();
});
