<?php

use App\Actions\Card\SendExpirationWarningsAction;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CardExpirationWarningNotification;
use App\Settings\CardSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeWarnCard(User $user, array $attrs = []): Card
{
    $order = Order::factory()->create(['user_id' => $user->id]);
    return Card::factory()->create(array_merge([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addDays(30),
    ], $attrs));
}

function setWarningDays(array $days): void
{
    $settings = app(CardSettings::class);
    $settings->expiration_warning_days = $days;
    $settings->save();
}

it('sends warning for active cards in the threshold window', function () {
    Notification::fake();
    setWarningDays([30]);

    $user = User::factory()->create();
    // expires_at exactly 30 days from now (within ±12h window)
    makeWarnCard($user, ['expires_at' => now()->addDays(30)]);

    $count = app(SendExpirationWarningsAction::class)->execute();

    expect($count)->toBe(1);
    Notification::assertSentTo($user, CardExpirationWarningNotification::class, function ($n) {
        return $n->daysUntilExpiration === 30;
    });
});

it('sends multiple warnings as different thresholds are crossed', function () {
    Notification::fake();
    setWarningDays([30, 7]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    makeWarnCard($userA, ['expires_at' => now()->addDays(30)]);
    makeWarnCard($userB, ['expires_at' => now()->addDays(7)]);

    $count = app(SendExpirationWarningsAction::class)->execute();

    expect($count)->toBe(2);
    Notification::assertSentTo($userA, CardExpirationWarningNotification::class);
    Notification::assertSentTo($userB, CardExpirationWarningNotification::class);
});

it('does NOT send the same threshold twice (idempotency via reminders_sent)', function () {
    Notification::fake();
    setWarningDays([30]);

    $user = User::factory()->create();
    $card = makeWarnCard($user, [
        'expires_at'     => now()->addDays(30),
        'reminders_sent' => [30], // already sent
    ]);

    $count = app(SendExpirationWarningsAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('does not send warning for already expired or future-far cards', function () {
    Notification::fake();
    setWarningDays([30]);

    $user = User::factory()->create();
    // expired card: should not be selected (status = Expired)
    makeWarnCard($user, ['expires_at' => now()->subDay(), 'status' => CardStatus::Expired]);
    // far future card: outside the ±12h window around 30 days
    makeWarnCard($user, ['expires_at' => now()->addDays(60)]);

    $count = app(SendExpirationWarningsAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('returns 0 immediately when expiration_warning_days is empty', function () {
    Notification::fake();
    setWarningDays([]);

    $user = User::factory()->create();
    makeWarnCard($user, ['expires_at' => now()->addDays(30)]);

    $count = app(SendExpirationWarningsAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});
