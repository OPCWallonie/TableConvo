<?php

use App\Actions\Invoice\GenerateInvoiceAction;
use App\Actions\Order\ProcessPaidOrderAction;
use App\Enums\CardStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendInvoiceByEmailJob;
use App\Models\CardType;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
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

function makePendingOrderWithItems(int $qty = 1): Order
{
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $cardType = CardType::factory()->create([
        'sessions_count' => 10,
        'validity_months' => 12,
        'price' => 250.00,
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
        'company_snapshot' => [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'street' => $company->street,
            'postal_code' => $company->postal_code,
            'city' => $company->city,
            'country' => $company->country,
        ],
        'total_ht' => 206.61,
        'total_vat' => 43.39,
        'total_ttc' => 250.00,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'card_type_id' => $cardType->id,
        'quantity' => $qty,
        'unit_price_ht' => 206.61,
        'vat_rate' => 21.00,
        'vat_amount' => 43.39,
        'total_ht' => 206.61 * $qty,
        'total_ttc' => 250.00 * $qty,
    ]);

    return $order;
}

it('marks the order as paid', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('creates the correct number of cards from quantity', function () {
    $order = makePendingOrderWithItems(qty: 3);

    app(ProcessPaidOrderAction::class)->execute($order);

    expect($order->cards()->count())->toBe(3);
});

it('creates cards with correct sessions_remaining', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    $card = $order->cards()->first();
    expect($card->sessions_remaining)->toBe(10);
    expect($card->sessions_total)->toBe(10);
});

it('creates cards with correct expires_at', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    $card = $order->cards()->first();
    expect($card->expires_at->toDateString())
        ->toBe(now()->addMonths(12)->toDateString());
});

it('creates cards with active status', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    expect($order->cards()->first()->status)->toBe(CardStatus::Active);
});

it('generates exactly one invoice', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    expect($order->invoice()->count())->toBe(1);
});

it('dispatches SendInvoiceByEmailJob', function () {
    $order = makePendingOrderWithItems();

    app(ProcessPaidOrderAction::class)->execute($order);

    Queue::assertPushed(SendInvoiceByEmailJob::class);
});
