<?php

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Resources\Waitlist\Pages\ListWaitlist;
use App\Filament\Resources\Waitlist\WaitlistResource;
use App\Models\ConversationTable;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeWaitlistResourceAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makeWaitlistRegistration(Level $level, ConversationTable $table, int $position = 1): Registration
{
    $user = User::factory()->withLevel($level)->create();

    return Registration::create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => null,
        'status'                => RegistrationStatus::Waitlist,
        'registered_at'         => now()->subMinutes(10 * $position),
        'waitlist_position'     => $position,
    ]);
}

it('admin can list all waitlist registrations across sessions', function () {
    $admin = makeWaitlistResourceAdmin();

    $level  = Level::factory()->create();
    $table1 = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);
    $table2 = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(14),
    ]);

    makeWaitlistRegistration($level, $table1, 1);
    makeWaitlistRegistration($level, $table2, 1);

    $this->actingAs($admin)->get(WaitlistResource::getUrl('index'))->assertSuccessful();
});

it('non-admin cannot access waitlist resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(WaitlistResource::getUrl('index'))->assertForbidden();
});

it('table is sorted by created_at ASC oldest first', function () {
    $admin = makeWaitlistResourceAdmin();

    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);

    $older = Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subHour(),
        'waitlist_position' => 1, 'created_at' => now()->subHour(),
    ]);

    $newer = Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(5),
        'waitlist_position' => 2, 'created_at' => now()->subMinutes(5),
    ]);

    $component = Livewire::actingAs($admin)->test(ListWaitlist::class);

    $component->assertSuccessful();

    // La query par défaut tri ASC sur created_at → l'older doit être premier
    $rows = $component->instance()->getTable()->getRecords();
    expect($rows->first()->id)->toBe($older->id);
});

it('filter by level filters correctly', function () {
    $admin = makeWaitlistResourceAdmin();

    $levelA = Level::factory()->create(['code' => 'A1']);
    $levelB = Level::factory()->create(['code' => 'B1']);

    $tableA = ConversationTable::factory()->create([
        'level_id' => $levelA->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);
    $tableB = ConversationTable::factory()->create([
        'level_id' => $levelB->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);

    $regA = makeWaitlistRegistration($levelA, $tableA);
    $regB = makeWaitlistRegistration($levelB, $tableB);

    $component = Livewire::actingAs($admin)
        ->test(ListWaitlist::class)
        ->set('tableFilters.level.value', $levelA->id);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($regA->id);
    expect($records->pluck('id'))->not->toContain($regB->id);
});

it('only future scheduled sessions are included', function () {
    $admin = makeWaitlistResourceAdmin();
    $level = Level::factory()->create();

    // Session future → doit apparaître
    $futureTable = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);
    $futureReg = makeWaitlistRegistration($level, $futureTable);

    // Session passée → ne doit PAS apparaître
    $pastTable = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->subDays(1),
    ]);
    Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $pastTable->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subDays(2), 'waitlist_position' => 1,
    ]);

    // Session annulée → ne doit PAS apparaître
    $cancelledTable = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Cancelled, 'scheduled_at' => now()->addDays(5),
    ]);
    Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $cancelledTable->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(5), 'waitlist_position' => 1,
    ]);

    $component = Livewire::actingAs($admin)->test(ListWaitlist::class);
    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($futureReg->id);
    expect($records)->toHaveCount(1);
});

it('the position accessor returns the correct rank within the session', function () {
    $level = Level::factory()->create();
    $table = ConversationTable::factory()->create([
        'level_id' => $level->id, 'status' => SessionStatus::Scheduled, 'scheduled_at' => now()->addDays(7),
    ]);

    $reg1 = Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(30),
        'waitlist_position' => 1, 'created_at' => now()->subMinutes(30),
    ]);
    $reg2 = Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(20),
        'waitlist_position' => 2, 'created_at' => now()->subMinutes(20),
    ]);
    $reg3 = Registration::create([
        'user_id' => User::factory()->withLevel($level)->create()->id,
        'conversation_table_id' => $table->id, 'card_id' => null,
        'status' => RegistrationStatus::Waitlist, 'registered_at' => now()->subMinutes(10),
        'waitlist_position' => 3, 'created_at' => now()->subMinutes(10),
    ]);

    expect($reg1->position)->toBe(1);
    expect($reg2->position)->toBe(2);
    expect($reg3->position)->toBe(3);
});
