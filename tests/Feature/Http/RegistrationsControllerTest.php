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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// 1. Accès protégé
// ─────────────────────────────────────────────────────────────

it('redirects guests to login', function () {
    $this->get(route('espace.inscriptions'))
        ->assertRedirect(route('login'));
});

// ─────────────────────────────────────────────────────────────
// 2. Inscriptions à venir
// ─────────────────────────────────────────────────────────────

it('shows upcoming registrations in the upcoming collection', function () {
    $level = Level::factory()->withCode('A2')->create();
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addYear(),
    ]);
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now(),
    ]);

    $response = $this->actingAs($user)->get(route('espace.inscriptions'));

    $response->assertOk();
    $response->assertViewIs('espace.inscriptions.index');
    expect($response->viewData('upcoming'))->toHaveCount(1);
    expect($response->viewData('upcoming')->first()->id)->toBe($table->id === null ? null : Registration::first()->id);
});

// ─────────────────────────────────────────────────────────────
// 3. Inscriptions passées
// ─────────────────────────────────────────────────────────────

it('shows past and cancelled registrations in the past collection', function () {
    $level = Level::factory()->withCode('B1')->create();
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'  => $user->id,
        'order_id' => $order->id,
        'status'   => CardStatus::Active,
        'expires_at' => now()->addYear(),
    ]);

    // Session passée avec statut Attended
    $pastTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Completed,
        'scheduled_at' => now()->subDays(7),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $pastTable->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Attended,
        'registered_at'         => now()->subDays(14),
    ]);

    // Inscription annulée (session future)
    $futureTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $futureTable->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Cancelled,
        'registered_at'         => now()->subDays(3),
        'cancelled_at'          => now()->subDays(2),
        'cancelled_by'          => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('espace.inscriptions'));

    $response->assertOk();
    expect($response->viewData('past'))->toHaveCount(2);
    expect($response->viewData('upcoming'))->toHaveCount(0);
});

// ─────────────────────────────────────────────────────────────
// 4. Séparation à venir / passé
// ─────────────────────────────────────────────────────────────

it('correctly separates upcoming from past registrations', function () {
    $level = Level::factory()->withCode('C1')->create();
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create([
        'user_id'    => $user->id,
        'order_id'   => $order->id,
        'status'     => CardStatus::Active,
        'expires_at' => now()->addYear(),
        'sessions_remaining' => 10,
        'sessions_total'     => 10,
    ]);

    // 1 inscription à venir (Registered + future)
    $upcoming = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $upcoming->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
        'registered_at'         => now(),
    ]);

    // 1 inscription passée (Registered + date passée → compte comme "passée")
    $past = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Completed,
        'scheduled_at' => now()->subDays(3),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $past->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Attended,
        'registered_at'         => now()->subDays(10),
    ]);

    // 1 annulation (status Cancelled + future) → passée aussi
    $cancelled = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(7),
    ]);
    Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $cancelled->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Cancelled,
        'registered_at'         => now()->subDay(),
        'cancelled_at'          => now(),
        'cancelled_by'          => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('espace.inscriptions'));

    $response->assertOk();
    expect($response->viewData('upcoming'))->toHaveCount(1);
    expect($response->viewData('past'))->toHaveCount(2);

    // Vérifier que les collections ne se chevauchent pas
    $upcomingIds = $response->viewData('upcoming')->pluck('id');
    $pastIds     = $response->viewData('past')->pluck('id');
    expect($upcomingIds->intersect($pastIds))->toBeEmpty();
});
