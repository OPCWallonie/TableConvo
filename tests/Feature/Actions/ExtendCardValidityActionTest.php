<?php

use App\Actions\Card\ExtendCardValidityAction;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('extends card validity by the given number of days', function () {
    $user  = User::factory()->create();
    $admin = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'    => $user->id,
        'order_id'   => $order->id,
        'status'     => CardStatus::Active,
        'expires_at' => now()->addDays(10),
    ]);

    $before = $card->expires_at->copy();

    $result = app(ExtendCardValidityAction::class)->execute($card, 30, $admin);

    expect($result->expires_at->toDateString())
        ->toBe($before->addDays(30)->toDateString());
});

it('can extend an already-expired card', function () {
    $user  = User::factory()->create();
    $admin = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'    => $user->id,
        'order_id'   => $order->id,
        'status'     => CardStatus::Expired,
        'expires_at' => now()->subDays(5),
    ]);

    $before = $card->expires_at->copy();

    $result = app(ExtendCardValidityAction::class)->execute($card, 30, $admin);

    expect($result->expires_at->toDateString())
        ->toBe($before->addDays(30)->toDateString());
});

it('logs the extension in the activity log', function () {
    $user  = User::factory()->create();
    $admin = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addDays(10),
    ]);

    app(ExtendCardValidityAction::class)->execute($card, 15, $admin);

    $log = \Spatie\Activitylog\Models\Activity::latest()->first();
    expect($log)->not->toBeNull();
    expect($log->properties['extended_by_days'])->toBe(15);
});
