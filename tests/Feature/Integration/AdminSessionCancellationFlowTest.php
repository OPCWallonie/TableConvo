<?php

use App\Enums\CardStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Resources\ConversationTables\Pages\ListConversationTables;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\CardExpirationWarningNotification;
use App\Notifications\SessionCancelledNotification;
use App\Notifications\SessionReminderNotification;
use App\Settings\BookingSettings;
use App\Settings\CardSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Test 1 — Flux complet d'annulation via UI Filament
// ─────────────────────────────────────────────────────────────

it('complete session cancellation flow notifies all users with correct compensation types', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->post_cancellation_extension_threshold_days = 30;
    $settings->post_cancellation_card_extension_days      = 30;
    $settings->save();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $level = Level::factory()->create(['code' => 'B2']);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);

    // Helpers locaux
    $mkCard = function (User $user, array $attrs) use ($table): Card {
        $order = Order::factory()->create(['user_id' => $user->id]);
        return Card::factory()->create(array_merge([
            'user_id'  => $user->id,
            'order_id' => $order->id,
        ], $attrs));
    };
    $mkRegistered = function (User $user, Card $card) use ($table): Registration {
        $reg = Registration::create([
            'user_id'               => $user->id,
            'conversation_table_id' => $table->id,
            'card_id'               => $card->id,
            'status'                => RegistrationStatus::Registered,
            'registered_at'         => now()->subDays(5),
        ]);
        $card->decrement('sessions_remaining');
        return $reg;
    };
    $mkWaitlist = function (User $user) use ($table): Registration {
        return Registration::create([
            'user_id'               => $user->id,
            'conversation_table_id' => $table->id,
            'card_id'               => null,
            'status'                => RegistrationStatus::Waitlist,
            'registered_at'         => now()->subDays(3),
            'waitlist_position'     => 1,
        ]);
    };

    // User 1 : carte active, expire dans 15 j (seuil 30 j → recredit_and_extend)
    $user1 = User::factory()->withLevel($level)->create();
    $card1 = $mkCard($user1, ['status' => CardStatus::Active, 'expires_at' => now()->addDays(15)]);
    $mkRegistered($user1, $card1);
    $remaining1Before = $card1->fresh()->sessions_remaining;
    $expires1Before   = $card1->fresh()->expires_at->copy();

    // User 2 : carte active, expire dans 90 j (hors seuil → recredit_only)
    $user2 = User::factory()->withLevel($level)->create();
    $card2 = $mkCard($user2, ['status' => CardStatus::Active, 'expires_at' => now()->addDays(90)]);
    $mkRegistered($user2, $card2);
    $remaining2Before = $card2->fresh()->sessions_remaining;
    $expires2Before   = $card2->fresh()->expires_at->copy();

    // User 3 : carte expirée (→ expired_no_compensation)
    $user3 = User::factory()->withLevel($level)->create();
    $card3 = $mkCard($user3, ['status' => CardStatus::Expired, 'expires_at' => now()->subDays(5)]);
    $mkRegistered($user3, $card3);
    $remaining3Before = $card3->fresh()->sessions_remaining;

    // User 4 : liste d'attente (→ waitlist_notice)
    $user4 = User::factory()->withLevel($level)->create();
    $reg4  = $mkWaitlist($user4);

    // --- Appel de l'action via Filament ---
    Livewire::actingAs($admin)
        ->test(ListConversationTables::class)
        ->callTableAction('cancel_session', $table, ['reason' => 'Indisponibilité animateur'])
        ->assertHasNoTableActionErrors();

    // Table annulée, raison persistée
    expect($table->fresh()->status)->toBe(SessionStatus::Cancelled);
    expect($table->fresh()->cancellation_reason)->toBe('Indisponibilité animateur');

    // Carte 1 : recréditée + prolongée de 30 j
    expect($card1->fresh()->sessions_remaining)->toBe($remaining1Before + 1);
    expect($card1->fresh()->expires_at->toDateString())
        ->toBe($expires1Before->addDays(30)->toDateString());

    // Carte 2 : recréditée, validité inchangée
    expect($card2->fresh()->sessions_remaining)->toBe($remaining2Before + 1);
    expect($card2->fresh()->expires_at->toDateString())->toBe($expires2Before->toDateString());

    // Carte 3 : inchangée (expirée → pas de recréditation)
    expect($card3->fresh()->sessions_remaining)->toBe($remaining3Before);

    // User 4 : inscription annulée, aucun crédit
    expect($reg4->fresh()->status)->toBe(RegistrationStatus::Cancelled);

    // 4 notifications avec les bons compensation_type
    Notification::assertSentTo($user1, SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'recredit_and_extend');
    Notification::assertSentTo($user2, SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'recredit_only');
    Notification::assertSentTo($user3, SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'expired_no_compensation');
    Notification::assertSentTo($user4, SessionCancelledNotification::class,
        fn (SessionCancelledNotification $n) => $n->compensationType === 'waitlist_notice');
    Notification::assertCount(4);
});

// ─────────────────────────────────────────────────────────────
// Test 2 — Auto-clôture de session après la grace period
// ─────────────────────────────────────────────────────────────

it('session is auto-closed and all remaining Registered become NoShow after grace period', function () {
    $settings = app(BookingSettings::class);
    $settings->auto_mark_noshow_after_days = 7;
    $settings->save();

    $level = Level::factory()->create(['code' => 'A2']);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->subDays(8),
    ]);

    $mkReg = function () use ($table) {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $card  = Card::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);
        return Registration::create([
            'user_id'               => $user->id,
            'conversation_table_id' => $table->id,
            'card_id'               => $card->id,
            'status'                => RegistrationStatus::Registered,
            'registered_at'         => now()->subDays(12),
        ]);
    };

    $reg1 = $mkReg();
    $reg2 = $mkReg();

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($table->fresh()->status)->toBe(SessionStatus::Completed);
    expect($reg1->fresh()->status)->toBe(RegistrationStatus::NoShow);
    expect($reg2->fresh()->status)->toBe(RegistrationStatus::NoShow);
});

// ─────────────────────────────────────────────────────────────
// Test 3 — Cycle complet alerte expiration + expiration effective
// ─────────────────────────────────────────────────────────────

it('card expiration warning is idempotent, then card expires when date passes', function () {
    Notification::fake();

    $cardSettings = app(CardSettings::class);
    $cardSettings->expiration_warning_days = [7];
    $cardSettings->save();

    $user  = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    // expires_at légèrement en dessous de 7 jours pour être dans la fenêtre ±12h
    // et pour être < now() après un voyage de 7 jours
    $card = Card::factory()->create([
        'user_id'    => $user->id,
        'order_id'   => $order->id,
        'status'     => CardStatus::Active,
        'expires_at' => now()->addDays(7)->subMinutes(30),
    ]);

    // Première exécution : alerte envoyée, reminders_sent = [7]
    Artisan::call('cards:warn-expiration');
    Notification::assertSentTo($user, CardExpirationWarningNotification::class,
        fn (CardExpirationWarningNotification $n) => $n->daysUntilExpiration === 7);
    expect($card->fresh()->reminders_sent)->toContain(7);
    Notification::assertCount(1);

    // Deuxième exécution le même jour : aucune nouvelle notification (idempotence)
    Artisan::call('cards:warn-expiration');
    Notification::assertCount(1);

    // 7 jours plus tard : expires_at est maintenant dans le passé
    $this->travel(7)->days();

    expect($card->fresh()->status)->toBe(CardStatus::Active);

    Artisan::call('cards:expire');

    expect($card->fresh()->status)->toBe(CardStatus::Expired);
});

// ─────────────────────────────────────────────────────────────
// Test 4 — Rappel de session envoyé une seule fois (idempotence)
// ─────────────────────────────────────────────────────────────

it('session reminder fires exactly once even if the command runs again minutes later', function () {
    Notification::fake();

    $settings = app(BookingSettings::class);
    $settings->session_reminder_hours_before = 24;
    $settings->save();

    $level = Level::factory()->create(['code' => 'C1']);
    $user  = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
    ]);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addHours(23.5), // dans la fenêtre [23h, 24h]
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now()->subDays(5),
    ]);

    // Premier tick : rappel envoyé
    Artisan::call('sessions:send-reminders');
    Notification::assertSentTo($user, SessionReminderNotification::class);
    Notification::assertCount(1);

    // 5 minutes plus tard : re-exécution du command
    $this->travel(5)->minutes();
    Artisan::call('sessions:send-reminders');

    // Toujours 1 seule notification (reminded_at empêche le doublon)
    Notification::assertCount(1);
});
