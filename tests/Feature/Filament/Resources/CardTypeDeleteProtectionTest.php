<?php

use App\Filament\Resources\CardTypes\Pages\EditCardType;
use App\Filament\Resources\CardTypes\Pages\ListCardTypes;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeCardTypeDeleteAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('allows card type deletion when no cards and no order items exist', function () {
    $admin = makeCardTypeDeleteAdmin();
    $cardType = CardType::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditCardType::class, ['record' => $cardType->getRouteKey()])
        ->callAction('delete');

    expect(CardType::find($cardType->id))->toBeNull();
});

it('blocks card type deletion when at least one card exists including trashed', function () {
    $admin = makeCardTypeDeleteAdmin();
    $cardType = CardType::factory()->create();

    $card = Card::factory()->for($cardType)->create();
    $card->delete(); // soft-delete : la ligne physique subsiste, FK toujours active

    Livewire::actingAs($admin)
        ->test(EditCardType::class, ['record' => $cardType->getRouteKey()])
        ->callAction('delete')
        ->assertNotified();

    expect(CardType::find($cardType->id))->not->toBeNull();
});

it('blocks card type deletion when at least one order item exists', function () {
    $admin = makeCardTypeDeleteAdmin();
    $cardType = CardType::factory()->create();

    // OrderItem n'a pas HasFactory — on le crée directement
    $order = Order::factory()->create();
    OrderItem::create([
        'order_id'      => $order->id,
        'card_type_id'  => $cardType->id,
        'quantity'      => 1,
        'unit_price_ht' => 206.61,
        'vat_rate'      => 21.00,
        'vat_amount'    => 43.39,
        'total_ht'      => 206.61,
        'total_ttc'     => 250.00,
    ]);

    Livewire::actingAs($admin)
        ->test(EditCardType::class, ['record' => $cardType->getRouteKey()])
        ->callAction('delete')
        ->assertNotified();

    expect(CardType::find($cardType->id))->not->toBeNull();
});

it('blocks bulk deletion if any selected card type has dependencies', function () {
    $admin = makeCardTypeDeleteAdmin();

    $protected = CardType::factory()->create();
    $free      = CardType::factory()->create();
    Card::factory()->for($protected)->create();

    Livewire::actingAs($admin)
        ->test(ListCardTypes::class)
        ->callTableBulkAction('delete', [$protected, $free])
        ->assertNotified();

    // L'opération entière est annulée — les deux types subsistent
    expect(CardType::find($protected->id))->not->toBeNull();
    expect(CardType::find($free->id))->not->toBeNull();
});
