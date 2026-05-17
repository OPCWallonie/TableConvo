<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Models\CardType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeOrderAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

it('admin can list orders', function () {
    $admin = makeOrderAdmin();
    Order::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(OrderResource::getUrl('index'))
        ->assertSuccessful();
});

it('non-admin cannot access the orders resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(OrderResource::getUrl('index'))
        ->assertForbidden();
});

it('filter status works', function () {
    $admin = makeOrderAdmin();

    $paid    = Order::factory()->create(['user_id' => $admin->id, 'status' => OrderStatus::Paid]);
    $pending = Order::factory()->create(['user_id' => $admin->id, 'status' => OrderStatus::Pending]);

    $component = Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->set('tableFilters.status.value', OrderStatus::Paid->value);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($paid->id);
    expect($records->pluck('id'))->not->toContain($pending->id);
});

it('filter current_month works', function () {
    $admin = makeOrderAdmin();

    $thisMonth = Order::factory()->create([
        'user_id'  => $admin->id,
        'status'   => OrderStatus::Paid,
        'paid_at'  => now(),
    ]);
    $lastMonth = Order::factory()->create([
        'user_id'  => $admin->id,
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonth(),
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->set('tableFilters.current_month.isActive', true);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($thisMonth->id);
    expect($records->pluck('id'))->not->toContain($lastMonth->id);
});

it('filter last_12_months works', function () {
    $admin = makeOrderAdmin();

    $recent = Order::factory()->create([
        'user_id'  => $admin->id,
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonths(6),
    ]);
    $old = Order::factory()->create([
        'user_id'  => $admin->id,
        'status'   => OrderStatus::Paid,
        'paid_at'  => now()->subMonths(13),
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->set('tableFilters.last_12_months.isActive', true);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($recent->id);
    expect($records->pluck('id'))->not->toContain($old->id);
});

it('search by user full_name works', function () {
    $admin = makeOrderAdmin();

    $userAlpha = User::factory()->create(['first_name' => 'Alphonse', 'last_name' => 'Renard']);
    $userBeta  = User::factory()->create(['first_name' => 'Béatrice', 'last_name' => 'Moreau']);

    Order::factory()->create(['user_id' => $userAlpha->id]);
    Order::factory()->create(['user_id' => $userBeta->id]);

    $component = Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->set('tableSearch', 'Alphonse');

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('user_id'))->toContain($userAlpha->id);
    expect($records->pluck('user_id'))->not->toContain($userBeta->id);
});

it('view order page renders with infolist content', function () {
    $admin = makeOrderAdmin();
    $order = Order::factory()->create([
        'user_id'   => $admin->id,
        'status'    => OrderStatus::Paid,
        'total_ht'  => 206.61,
        'paid_at'   => now(),
    ]);

    $this->actingAs($admin)
        ->get(OrderResource::getUrl('view', ['record' => $order]))
        ->assertSuccessful()
        ->assertSee('Informations commande')
        ->assertSee('Détails financiers');
});

it('no create action is exposed on the orders resource', function () {
    $admin = makeOrderAdmin();

    expect(OrderResource::canCreate())->toBeFalse();
});

it('order items relation manager renders items', function () {
    $admin    = makeOrderAdmin();
    $order    = Order::factory()->create(['user_id' => $admin->id]);
    $cardType = CardType::factory()->create(['name' => 'Carte 10 séances']);

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
        ->test(OrderItemsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass'   => ViewOrder::class,
        ])
        ->assertSee('Carte 10 séances');
});
