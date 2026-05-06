<?php

use App\Actions\Session\MarkAttendanceAction;
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

function makeAttendanceSetup(int $participantCount = 3): array
{
    $level = Level::factory()->create();
    $admin = User::factory()->create();

    $table = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subHour(),
        'status'       => SessionStatus::Scheduled,
    ]);

    $users = User::factory()->count($participantCount)->withLevel($level)->create();
    $registrations = $users->map(function ($user) use ($table) {
        $order = Order::factory()->create(['user_id' => $user->id]);
        $card  = Card::factory()->create([
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => CardStatus::Active,
            'expires_at' => now()->addMonths(6),
        ]);

        return Registration::create([
            'user_id'               => $user->id,
            'conversation_table_id' => $table->id,
            'card_id'               => $card->id,
            'status'                => RegistrationStatus::Registered,
            'registered_at'         => now()->subDay(),
        ]);
    });

    return compact('admin', 'table', 'users', 'registrations');
}

it('marks present users as attended and absent ones as no_show', function () {
    ['admin' => $admin, 'table' => $table, 'users' => $users, 'registrations' => $regs] = makeAttendanceSetup(3);

    $attendedIds = [$users[0]->id, $users[1]->id];

    app(MarkAttendanceAction::class)->execute($table, $attendedIds, $admin);

    expect($regs[0]->fresh()->status)->toBe(RegistrationStatus::Attended);
    expect($regs[1]->fresh()->status)->toBe(RegistrationStatus::Attended);
    expect($regs[2]->fresh()->status)->toBe(RegistrationStatus::NoShow);
});

it('marks session as completed after attendance is recorded', function () {
    ['admin' => $admin, 'table' => $table, 'users' => $users] = makeAttendanceSetup(2);

    app(MarkAttendanceAction::class)->execute($table, [$users[0]->id], $admin);

    expect($table->fresh()->status)->toBe(SessionStatus::Completed);
});

it('marks all as no_show when attended list is empty', function () {
    ['admin' => $admin, 'table' => $table, 'registrations' => $regs] = makeAttendanceSetup(2);

    app(MarkAttendanceAction::class)->execute($table, [], $admin);

    foreach ($regs as $reg) {
        expect($reg->fresh()->status)->toBe(RegistrationStatus::NoShow);
    }
});
