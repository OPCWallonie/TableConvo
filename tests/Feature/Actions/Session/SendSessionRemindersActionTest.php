<?php

use App\Actions\Session\SendSessionRemindersAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\SessionReminderNotification;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeReminderSetup(int $hoursUntilSession, ?string $remindedAt = null): array
{
    $settings = app(BookingSettings::class);
    $settings->session_reminder_hours_before = 24;
    $settings->save();

    $level = Level::factory()->create(['code' => 'B2']);
    $user  = User::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addHours($hoursUntilSession),
    ]);

    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
    ]);

    $reg = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subDays(3),
        'reminded_at'           => $remindedAt,
    ]);

    return compact('user', 'reg');
}

it('sends reminder for registration whose session is in the reminder window', function () {
    Notification::fake();

    // 24h setting → window is [23h, 24h] from now → 23.5h is inside
    ['user' => $user, 'reg' => $reg] = makeReminderSetup(hoursUntilSession: 23.5);

    $count = app(SendSessionRemindersAction::class)->execute();

    expect($count)->toBe(1);
    Notification::assertSentTo($user, SessionReminderNotification::class);
});

it('does not send if already reminded (reminded_at not null)', function () {
    Notification::fake();

    ['user' => $user] = makeReminderSetup(
        hoursUntilSession: 23.5,
        remindedAt: now()->subHour()->toDateTimeString()
    );

    $count = app(SendSessionRemindersAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('does not send for Cancelled or Waitlist registrations', function () {
    Notification::fake();

    $level = Level::factory()->create(['code' => 'A1']);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addHours(23.5),
    ]);

    $mkReg = function (RegistrationStatus $status) use ($table) {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $card  = Card::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => CardStatus::Active]);
        Registration::create([
            'user_id'               => $user->id,
            'conversation_table_id' => $table->id,
            'card_id'               => $card->id,
            'status'                => $status,
            'registered_at'         => now()->subDays(3),
        ]);
    };

    $mkReg(RegistrationStatus::Cancelled);
    $mkReg(RegistrationStatus::Waitlist);

    $count = app(SendSessionRemindersAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('does not send for sessions too far in the future (beyond the window)', function () {
    Notification::fake();

    // 24h setting → window is [23h, 24h] → 25h is OUTSIDE (too far)
    makeReminderSetup(hoursUntilSession: 25);

    $count = app(SendSessionRemindersAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('does not send for sessions already too close (below window start)', function () {
    Notification::fake();

    // 24h setting → window is [23h, 24h] → 22h is OUTSIDE (too close)
    makeReminderSetup(hoursUntilSession: 22);

    $count = app(SendSessionRemindersAction::class)->execute();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('updates reminded_at after sending the notification', function () {
    Notification::fake();

    ['reg' => $reg] = makeReminderSetup(hoursUntilSession: 23.5);

    expect($reg->fresh()->reminded_at)->toBeNull();

    app(SendSessionRemindersAction::class)->execute();

    expect($reg->fresh()->reminded_at)->not->toBeNull();
});
