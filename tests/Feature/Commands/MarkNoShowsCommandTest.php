<?php

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Settings\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeNoShowTable(int $daysAgo): ConversationTable
{
    $level = Level::factory()->create();
    return ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->subDays($daysAgo),
    ]);
}

function addNoShowRegistration(ConversationTable $table, RegistrationStatus $status): Registration
{
    $user  = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    $card  = Card::factory()->create(['user_id' => $user->id, 'order_id' => $order->id]);

    return Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => $status,
        'registered_at'         => now()->subDays(10),
    ]);
}

it('marks remaining Registered as NoShow for sessions older than N days', function () {
    $settings = app(BookingSettings::class);
    $settings->auto_mark_noshow_after_days = 7;
    $settings->save();

    $table = makeNoShowTable(daysAgo: 8);
    $reg   = addNoShowRegistration($table, RegistrationStatus::Registered);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($reg->fresh()->status)->toBe(RegistrationStatus::NoShow);
});

it('updates table status to Completed after marking no-shows', function () {
    $table = makeNoShowTable(daysAgo: 8);
    addNoShowRegistration($table, RegistrationStatus::Registered);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($table->fresh()->status)->toBe(SessionStatus::Completed);
});

it('ignores sessions already Completed', function () {
    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Completed,
        'scheduled_at' => now()->subDays(10),
    ]);
    $reg = addNoShowRegistration($table, RegistrationStatus::Registered);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($reg->fresh()->status)->toBe(RegistrationStatus::Registered);
});

it('ignores sessions still within the grace period', function () {
    $settings = app(BookingSettings::class);
    $settings->auto_mark_noshow_after_days = 7;
    $settings->save();

    $table = makeNoShowTable(daysAgo: 5); // only 5 days ago, grace = 7
    $reg   = addNoShowRegistration($table, RegistrationStatus::Registered);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($reg->fresh()->status)->toBe(RegistrationStatus::Registered);
    expect($table->fresh()->status)->toBe(SessionStatus::Scheduled);
});

it('preserves Attended and Cancelled registrations — only Registered becomes NoShow', function () {
    $table     = makeNoShowTable(daysAgo: 8);
    $regReg    = addNoShowRegistration($table, RegistrationStatus::Registered);
    $regAtt    = addNoShowRegistration($table, RegistrationStatus::Attended);
    $regCancel = addNoShowRegistration($table, RegistrationStatus::Cancelled);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    expect($regReg->fresh()->status)->toBe(RegistrationStatus::NoShow);
    expect($regAtt->fresh()->status)->toBe(RegistrationStatus::Attended);
    expect($regCancel->fresh()->status)->toBe(RegistrationStatus::Cancelled);
});

it('logs activity on each auto-closed session', function () {
    $table = makeNoShowTable(daysAgo: 8);
    addNoShowRegistration($table, RegistrationStatus::Registered);

    $this->artisan('attendance:mark-no-shows')->assertSuccessful();

    $log = \Spatie\Activitylog\Models\Activity::where('subject_type', ConversationTable::class)
        ->where('subject_id', $table->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toContain('NoShow');
});
