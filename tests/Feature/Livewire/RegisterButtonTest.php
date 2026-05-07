<?php

use App\Actions\User\RequestLevelInterviewAction;
use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Livewire\Agenda\RegisterButton;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\NotifyAdminOfLevelInterviewNeeded;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────

function makeBookingSetup(): array
{
    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours      = 24;
    $settings->cancellation_deadline_business_days = 3;
    $settings->max_registrations_per_week       = 1;
    $settings->max_future_registrations         = 3;
    $settings->save();

    $level = Level::factory()->withCode('B1')->create();
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'            => $user->id,
        'order_id'           => $order->id,
        'sessions_remaining' => 5,
        'status'             => CardStatus::Active,
        'expires_at'         => now()->addMonths(6),
    ]);
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'status'           => SessionStatus::Scheduled,
        'scheduled_at'     => now()->addDays(7),
        'max_participants' => 4,
    ]);

    return compact('level', 'user', 'card', 'table');
}

function makeAdminForRegisterButton(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

// ─────────────────────────────────────────────────────────────
// 1. État guest
// ─────────────────────────────────────────────────────────────

it('shows guest state when user is not authenticated', function () {
    ['table' => $table] = makeBookingSetup();

    Livewire::test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'guest');
});

// ─────────────────────────────────────────────────────────────
// 2. État registered / waitlisted
// ─────────────────────────────────────────────────────────────

it('shows registered state when user already has a confirmed registration', function () {
    ['user' => $user, 'card' => $card, 'table' => $table] = makeBookingSetup();

    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now(),
    ]);

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'registered');
});

it('shows waitlisted state when user is already on the waitlist', function () {
    ['user' => $user, 'table' => $table] = makeBookingSetup();

    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now(),
        'waitlist_position'     => 2,
    ]);

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'waitlisted')
        ->assertSet('waitlistPosition', 2);
});

// ─────────────────────────────────────────────────────────────
// 3. État can_register → inscription réussie
// ─────────────────────────────────────────────────────────────

it('shows can_register state and registers the user successfully', function () {
    ['user' => $user, 'card' => $card, 'table' => $table] = makeBookingSetup();

    $before = $card->fresh()->sessions_remaining;

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'can_register')
        ->call('register')
        ->assertSet('status', 'registered')
        ->assertSet('flashMessage', "Votre inscription est confirmée !");

    expect($card->fresh()->sessions_remaining)->toBe($before - 1);
});

// ─────────────────────────────────────────────────────────────
// 4. État can_waitlist → inscription en liste d'attente
// ─────────────────────────────────────────────────────────────

it('shows can_waitlist when table is full and joins waitlist on click', function () {
    ['level' => $level, 'user' => $user, 'table' => $table] = makeBookingSetup();

    // Remplir la table (max_participants = 4)
    for ($i = 0; $i < 4; $i++) {
        $other = User::factory()->withLevel($level)->create();
        $order = Order::factory()->create(['user_id' => $other->id]);
        $c = Card::factory()->create([
            'user_id'            => $other->id,
            'order_id'           => $order->id,
            'sessions_remaining' => 5,
            'status'             => CardStatus::Active,
            'expires_at'         => now()->addMonths(6),
        ]);
        Registration::create([
            'user_id'               => $other->id,
            'conversation_table_id' => $table->id,
            'card_id'               => $c->id,
            'status'                => RegistrationStatus::Registered,
            'registered_at'         => now(),
        ]);
    }

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'can_waitlist')
        ->call('joinWaitlist')
        ->assertSet('status', 'waitlisted')
        ->assertSetStrict('waitlistPosition', 1);
});

// ─────────────────────────────────────────────────────────────
// 5. État no_level → notification admin (première tentative)
// ─────────────────────────────────────────────────────────────

it('triggers interview notification on first register attempt without level', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->max_registrations_per_week  = 1;
    $settings->max_future_registrations    = 3;
    $settings->save();

    $admin = makeAdminForRegisterButton();
    $level = Level::factory()->withCode('C1')->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);
    $user = User::factory()->create(['level_id' => null]); // pas de niveau

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'no_level')
        ->call('register')
        ->assertSet('errorCode', 'no_level');

    Notification::assertSentTo($admin, NotifyAdminOfLevelInterviewNeeded::class);
    expect($user->fresh()->interview_requested_at)->not->toBeNull();
});

it('does not send a second notification when user without level tries again', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->max_registrations_per_week  = 1;
    $settings->max_future_registrations    = 3;
    $settings->save();

    $admin = makeAdminForRegisterButton();
    $level = Level::factory()->withCode('C2')->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);
    $user = User::factory()->create(['level_id' => null]);

    // Première tentative → notification envoyée
    app(RequestLevelInterviewAction::class)->execute($user);

    // Deuxième tentative via le composant
    Livewire::actingAs($user->fresh())
        ->test(RegisterButton::class, ['table' => $table])
        ->call('register');

    // Toujours exactement 1 notification, pas 2
    Notification::assertSentToTimes($admin, NotifyAdminOfLevelInterviewNeeded::class, 1);
});

// ─────────────────────────────────────────────────────────────
// 6. États blocked — mauvais niveau, pas de carte
// ─────────────────────────────────────────────────────────────

it('shows blocked with wrong_level when user level does not match the table', function () {
    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->max_registrations_per_week  = 1;
    $settings->max_future_registrations    = 3;
    $settings->save();

    $userLevel  = Level::factory()->withCode('A1')->create();
    $tableLevel = Level::factory()->withCode('C1')->create();
    $user  = User::factory()->withLevel($userLevel)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $tableLevel->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'blocked')
        ->assertSet('errorCode', 'wrong_level');
});

it('shows blocked with no_active_card when user has no card with remaining sessions', function () {
    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->max_registrations_per_week  = 1;
    $settings->max_future_registrations    = 3;
    $settings->save();

    $level = Level::factory()->withCode('B2')->create();
    $user  = User::factory()->withLevel($level)->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);
    // Pas de carte créée pour cet utilisateur

    Livewire::actingAs($user)
        ->test(RegisterButton::class, ['table' => $table])
        ->assertSet('status', 'blocked')
        ->assertSet('errorCode', 'no_active_card');
});
