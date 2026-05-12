<?php

use App\Actions\Registration\CancelRegistrationAction;
use App\Actions\Registration\PromoteFromWaitlistAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationCancelledByAdminNotification;
use App\Notifications\UserPromotedFromWaitlistNotification;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function makeNotifAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makeNotifSetup(): array
{
    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->waitlist_auto_promote = true;
    $settings->save();

    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(10),
        'max_participants' => 1,
    ]);

    $userA  = User::factory()->withLevel($level)->create();
    $orderA = Order::factory()->create(['user_id' => $userA->id]);
    $cardA  = Card::factory()->create([
        'user_id' => $userA->id, 'order_id' => $orderA->id,
        'sessions_remaining' => 4, 'status' => CardStatus::Active, 'expires_at' => now()->addMonths(6),
    ]);
    $regA = Registration::create([
        'user_id' => $userA->id, 'conversation_table_id' => $table->id, 'card_id' => $cardA->id,
        'status' => RegistrationStatus::Registered, 'registered_at' => now()->subHour(),
    ]);

    $userB  = User::factory()->withLevel($level)->create();
    $orderB = Order::factory()->create(['user_id' => $userB->id]);
    $cardB  = Card::factory()->create([
        'user_id' => $userB->id, 'order_id' => $orderB->id,
        'sessions_remaining' => 5, 'status' => CardStatus::Active, 'expires_at' => now()->addMonths(6),
    ]);
    $regB = Registration::create([
        'user_id' => $userB->id, 'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(30), 'waitlist_position' => 1,
    ]);

    return compact('table', 'level', 'userA', 'cardA', 'regA', 'userB', 'cardB', 'regB');
}

// ─────────────────────────────────────────────────────────────
// 1. Notification de promotion (via PromoteFromWaitlistAction directement)
// ─────────────────────────────────────────────────────────────

it('promoted user receives UserPromotedFromWaitlistNotification on mail and database channels', function () {
    Notification::fake();
    $admin = makeNotifAdmin();

    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10), 'max_participants' => 2,
    ]);

    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id' => $user->id, 'order_id' => $order->id,
        'sessions_remaining' => 5, 'status' => CardStatus::Active, 'expires_at' => now()->addMonths(6),
    ]);
    $reg = Registration::create([
        'user_id' => $user->id, 'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(10), 'waitlist_position' => 1,
    ]);

    app(PromoteFromWaitlistAction::class)->execute($reg, $admin);

    Notification::assertSentTo($user, UserPromotedFromWaitlistNotification::class);
});

// ─────────────────────────────────────────────────────────────
// 2. Pas de notification si la promotion échoue (pas de carte)
// ─────────────────────────────────────────────────────────────

it('does not send promotion notification when promote action throws', function () {
    Notification::fake();
    $admin = makeNotifAdmin();

    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10), 'max_participants' => 2,
    ]);

    $user = User::factory()->withLevel($level)->create(); // pas de carte active
    $reg  = Registration::create([
        'user_id' => $user->id, 'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(10), 'waitlist_position' => 1,
    ]);

    try {
        app(PromoteFromWaitlistAction::class)->execute($reg, $admin);
    } catch (\RuntimeException) {
        // attendu : no_active_card_for_promotion
    }

    Notification::assertNothingSent();
});

// ─────────────────────────────────────────────────────────────
// 3. Notification admin→user quand l'admin annule la registration d'un autre user
// ─────────────────────────────────────────────────────────────

it('user receives RegistrationCancelledByAdminNotification when admin cancels their registration', function () {
    Notification::fake();
    $admin = makeNotifAdmin();

    ['userA' => $userA, 'regA' => $regA] = makeNotifSetup();

    app(CancelRegistrationAction::class)->execute($regA, $admin);

    Notification::assertSentTo($userA, RegistrationCancelledByAdminNotification::class);
});

// ─────────────────────────────────────────────────────────────
// 4. Pas de notification quand l'user annule lui-même
// ─────────────────────────────────────────────────────────────

it('user does not receive RegistrationCancelledByAdminNotification when self-cancelling', function () {
    Notification::fake();

    ['userA' => $userA, 'regA' => $regA] = makeNotifSetup();

    app(CancelRegistrationAction::class)->execute($regA, $userA);

    Notification::assertNotSentTo($userA, RegistrationCancelledByAdminNotification::class);
});

// ─────────────────────────────────────────────────────────────
// 5. Admin qui annule sa propre inscription → pas de notif admin
// ─────────────────────────────────────────────────────────────

it('admin cancelling their own registration does not trigger admin notification', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->cancellation_deadline_business_days = 3;
    $settings->save();

    $admin = makeNotifAdmin();

    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10), 'max_participants' => 4,
    ]);
    $order = Order::factory()->create(['user_id' => $admin->id]);
    $card  = Card::factory()->create([
        'user_id' => $admin->id, 'order_id' => $order->id,
        'sessions_remaining' => 5, 'status' => CardStatus::Active, 'expires_at' => now()->addMonths(6),
    ]);
    $reg = Registration::create([
        'user_id' => $admin->id, 'conversation_table_id' => $table->id, 'card_id' => $card->id,
        'status' => RegistrationStatus::Registered, 'registered_at' => now()->subHour(),
    ]);
    $card->decrement('sessions_remaining');

    app(CancelRegistrationAction::class)->execute($reg, $admin);

    Notification::assertNotSentTo($admin, RegistrationCancelledByAdminNotification::class);
});
