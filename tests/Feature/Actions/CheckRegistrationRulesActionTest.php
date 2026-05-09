<?php

use App\Actions\Registration\CheckRegistrationRulesAction;
use App\Enums\CardStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\User;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers locaux
// ─────────────────────────────────────────────────────────────────────────────

function rulesUserWithCard(Level $level): User
{
    $user  = User::factory()->withLevel($level)->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    Card::factory()->create([
        'user_id'             => $user->id,
        'order_id'            => $order->id,
        'status'              => CardStatus::Active,
        'expires_at'          => now()->addMonths(6),
        'sessions_remaining'  => 5,
    ]);
    return $user;
}

function scheduledTable(Level $level, int $daysAhead = 7): ConversationTable
{
    return ConversationTable::factory()->create([
        'level_id'       => $level->id,
        'scheduled_at'   => now()->addDays($daysAhead),
        'status'         => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// session_not_open_for_registration
// ─────────────────────────────────────────────────────────────────────────────

it('returns session_not_open_for_registration for a cancelled session', function () {
    $level = Level::factory()->create();
    $user  = rulesUserWithCard($level);
    $table = scheduledTable($level);
    $table->update(['status' => SessionStatus::Cancelled]);

    $result = app(CheckRegistrationRulesAction::class)->execute($user, $table);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('session_not_open_for_registration');
});

it('returns session_not_open_for_registration for a completed session', function () {
    $level = Level::factory()->create();
    $user  = rulesUserWithCard($level);
    $table = scheduledTable($level);
    $table->update(['status' => SessionStatus::Completed]);

    $result = app(CheckRegistrationRulesAction::class)->execute($user, $table);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('session_not_open_for_registration');
});

// ─────────────────────────────────────────────────────────────────────────────
// session_already_passed
// ─────────────────────────────────────────────────────────────────────────────

it('returns session_already_passed for a scheduled session in the past', function () {
    $level = Level::factory()->create();
    $user  = rulesUserWithCard($level);

    // Session Scheduled mais dans le passé (le cron ne l'a pas encore clôturée)
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => now()->subDays(1),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);

    $result = app(CheckRegistrationRulesAction::class)->execute($user, $table);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('session_already_passed');
});

// ─────────────────────────────────────────────────────────────────────────────
// Chemin nominal — inscription autorisée
// ─────────────────────────────────────────────────────────────────────────────

it('allows registration when all rules pass', function () {
    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours  = 24;
    $settings->max_registrations_per_week   = 3;
    $settings->max_future_registrations     = 5;
    $settings->save();

    $level = Level::factory()->create();
    $user  = rulesUserWithCard($level);
    $table = scheduledTable($level, daysAhead: 7);

    $result = app(CheckRegistrationRulesAction::class)->execute($user, $table);

    expect($result['allowed'])->toBeTrue();
    expect($result['reason'])->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// deadline_passed — session trop proche (mais pas encore passée)
// ─────────────────────────────────────────────────────────────────────────────

it('returns deadline_passed when session is too close but not yet past', function () {
    $settings = app(BookingSettings::class);
    $settings->registration_deadline_hours = 24;
    $settings->save();

    $level = Level::factory()->create();
    $user  = rulesUserWithCard($level);

    // 12h dans le futur → dans la fenêtre d'exclusion (< 24h), mais pas encore passé
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => now()->addHours(12),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 8,
    ]);

    $result = app(CheckRegistrationRulesAction::class)->execute($user, $table);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('deadline_passed');
});
