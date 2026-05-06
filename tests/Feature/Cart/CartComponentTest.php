<?php

use App\Livewire\Cart\CartComponent;
use App\Models\CardType;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(InvoicingSettings::class);
    $settings->default_vat_rate = 21.00;
    $settings->save();
});

it('starts with empty cart', function () {
    $component = Livewire::test(CartComponent::class);
    expect($component->get('items'))->toBe([]);
});

it('adds an item to the cart', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 1)
        ->assertSet('items', [(string) $cardType->id => 1]);
});

it('increments quantity when adding existing item', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 1)
        ->call('addItem', $cardType->id, 1)
        ->assertSet('items', [(string) $cardType->id => 2]);
});

it('updates quantity', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 3)
        ->call('updateQuantity', $cardType->id, 5)
        ->assertSet('items', [(string) $cardType->id => 5]);
});

it('removes item when quantity set to 0', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 2)
        ->call('updateQuantity', $cardType->id, 0)
        ->assertSet('items', []);
});

it('removes a specific item', function () {
    $ct1 = CardType::factory()->create(['price' => 250.00]);
    $ct2 = CardType::factory()->create(['price' => 300.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $ct1->id, 1)
        ->call('addItem', $ct2->id, 1)
        ->call('removeItem', $ct1->id)
        ->assertSet('items', [(string) $ct2->id => 1]);
});

it('clears the cart', function () {
    $ct1 = CardType::factory()->create(['price' => 250.00]);
    $ct2 = CardType::factory()->create(['price' => 300.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $ct1->id, 1)
        ->call('addItem', $ct2->id, 2)
        ->call('clear')
        ->assertSet('items', []);
});

it('calculates totals correctly with vat 21%', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    $component = Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 2);

    $instance = $component->instance();
    $expectedTtc = 500.00;
    $expectedHt = round(500.00 / 1.21, 2, PHP_ROUND_HALF_UP);
    $expectedVat = round(500.00 - $expectedHt, 2, PHP_ROUND_HALF_UP);

    expect($instance->totalTtc)->toBe($expectedTtc);
    expect($instance->totalHt)->toBe($expectedHt);
    expect($instance->totalVat)->toBe($expectedVat);
});

it('persists items to session', function () {
    $cardType = CardType::factory()->create(['price' => 250.00]);

    Livewire::test(CartComponent::class)
        ->call('addItem', $cardType->id, 1);

    expect(session('cart.items'))->toBe([(string) $cardType->id => 1]);
});
