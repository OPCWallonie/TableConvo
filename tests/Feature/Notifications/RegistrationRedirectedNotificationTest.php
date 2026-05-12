<?php

use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationRedirectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function makeRedirectNotifSetup(): array
{
    $level = Level::factory()->create();

    $oldTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'topic'        => 'Session A',
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10),
    ]);

    $newTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'topic'        => 'Session B',
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(20),
    ]);

    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'status'             => CardStatus::Active,
        'sessions_remaining' => 5,
        'expires_at'         => now()->addMonths(6),
    ]);

    $registration = Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $newTable->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subMinutes(10),
        'waitlist_position'     => 2,
    ]);

    return compact('user', 'oldTable', 'newTable', 'registration', 'level', 'card');
}

it('mail subject contains réorienté', function () {
    ['user' => $user, 'oldTable' => $oldTable, 'registration' => $registration] = makeRedirectNotifSetup();

    $notification = new RegistrationRedirectedNotification($oldTable, $registration);
    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->subject)->toContain('réorienté');
});

it('mail body mentions both old and new session topics', function () {
    ['user' => $user, 'oldTable' => $oldTable, 'registration' => $registration] = makeRedirectNotifSetup();

    $notification = new RegistrationRedirectedNotification($oldTable, $registration);
    $mail = $notification->toMail($user);

    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('Session A');
    expect($body)->toContain('Session B');
});

it('mail body mentions the new position in waitlist', function () {
    ['user' => $user, 'oldTable' => $oldTable, 'registration' => $registration] = makeRedirectNotifSetup();

    $notification = new RegistrationRedirectedNotification($oldTable, $registration);
    $mail = $notification->toMail($user);

    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('2');
});

it('notification implements ShouldQueue', function () {
    ['oldTable' => $oldTable, 'registration' => $registration] = makeRedirectNotifSetup();

    $notification = new RegistrationRedirectedNotification($oldTable, $registration);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
