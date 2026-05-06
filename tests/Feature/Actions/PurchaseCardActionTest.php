<?php

use App\Actions\Card\PurchaseCardAction;
use App\Enums\CardStatus;
use App\Models\CardType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a card with sessions_total matching card_type sessions_count', function () {
    $user = User::factory()->create();
    $cardType = CardType::factory()->create(['sessions_count' => 10, 'validity_months' => 12]);
    $order = Order::factory()->create(['user_id' => $user->id]);

    $card = app(PurchaseCardAction::class)->execute($user, $cardType, $order);

    expect($card->sessions_total)->toBe(10);
    expect($card->sessions_remaining)->toBe(10);
});

it('sets expires_at to now + validity_months', function () {
    $user = User::factory()->create();
    $cardType = CardType::factory()->create(['sessions_count' => 10, 'validity_months' => 6]);
    $order = Order::factory()->create(['user_id' => $user->id]);

    $card = app(PurchaseCardAction::class)->execute($user, $cardType, $order);

    expect($card->expires_at->toDateString())
        ->toBe(now()->addMonths(6)->toDateString());
});

it('creates card with active status and correct price snapshot', function () {
    $user = User::factory()->create();
    $cardType = CardType::factory()->create(['price' => 250.00]);
    $order = Order::factory()->create(['user_id' => $user->id]);

    $card = app(PurchaseCardAction::class)->execute($user, $cardType, $order);

    expect($card->status)->toBe(CardStatus::Active);
    expect((float) $card->price_paid)->toBe(250.00);
    expect($card->user_id)->toBe($user->id);
    expect($card->order_id)->toBe($order->id);
});
