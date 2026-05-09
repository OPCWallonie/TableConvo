<?php

use App\Actions\Card\ExpireCardAction;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeExpireCard(User $user, array $attrs = []): Card
{
    $order = Order::factory()->create(['user_id' => $user->id]);
    return Card::factory()->create(array_merge([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->subDay(),
    ], $attrs));
}

it('expires only active cards past their expiration date', function () {
    $user = User::factory()->create();
    $expired = makeExpireCard($user, ['expires_at' => now()->subDay(), 'status' => CardStatus::Active]);
    $future  = makeExpireCard($user, ['expires_at' => now()->addDay(), 'status' => CardStatus::Active]);

    app(ExpireCardAction::class)->execute();

    expect($expired->fresh()->status)->toBe(CardStatus::Expired);
    expect($future->fresh()->status)->toBe(CardStatus::Active);
});

it('does not touch already expired cards', function () {
    $user = User::factory()->create();
    $card = makeExpireCard($user, ['status' => CardStatus::Expired, 'expires_at' => now()->subDays(10)]);

    app(ExpireCardAction::class)->execute();

    // Still Expired, not touched again
    expect($card->fresh()->status)->toBe(CardStatus::Expired);
    expect(\Spatie\Activitylog\Models\Activity::count())->toBe(0);
});

it('does not touch cards with future expires_at', function () {
    $user = User::factory()->create();
    $card = makeExpireCard($user, ['status' => CardStatus::Active, 'expires_at' => now()->addDays(5)]);

    app(ExpireCardAction::class)->execute();

    expect($card->fresh()->status)->toBe(CardStatus::Active);
});

it('logs activity on each expired card', function () {
    $user = User::factory()->create();
    makeExpireCard($user, ['expires_at' => now()->subHour()]);
    makeExpireCard($user, ['expires_at' => now()->subDays(2)]);

    app(ExpireCardAction::class)->execute();

    expect(\Spatie\Activitylog\Models\Activity::count())->toBe(2);
    expect(\Spatie\Activitylog\Models\Activity::first()->description)
        ->toBe('Carte expirée automatiquement');
});

it('returns the correct count of expired cards', function () {
    $user = User::factory()->create();
    makeExpireCard($user, ['expires_at' => now()->subDay()]);
    makeExpireCard($user, ['expires_at' => now()->subDays(5)]);
    makeExpireCard($user, ['expires_at' => now()->addDays(1), 'status' => CardStatus::Active]); // future

    $count = app(ExpireCardAction::class)->execute();

    expect($count)->toBe(2);
});
