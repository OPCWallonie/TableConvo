<?php

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Livewire\Admin\RegistrationsManager;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeManagerAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makeManagerSetup(): array
{
    $admin = makeManagerAdmin();
    $level = Level::factory()->create(['code' => 'B1', 'sort_order' => 3]);
    $user  = User::factory()->create(['level_id' => $level->id]);
    $table = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'max_participants' => 8,
        'scheduled_at'     => now()->addDays(7),
        'status'           => SessionStatus::Scheduled,
    ]);

    return compact('admin', 'level', 'user', 'table');
}

it('openRemoveDialog sets state correctly for a registered inscription', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeManagerSetup();

    $card = Card::factory()->create(['user_id' => $user->id]);
    $reg  = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(RegistrationsManager::class, ['table' => $table])
        ->call('openRemoveDialog', $reg->id);

    expect($component->get('removeRegistrationId'))->toBe($reg->id);
});

it('confirmRemove with "reorient" choice calls MoveRegistrationAction', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table, 'level' => $level] = makeManagerSetup();

    $card = Card::factory()->create(['user_id' => $user->id]);
    $reg  = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Waitlist,
        'waitlist_position'     => 1,
    ]);

    $otherTable = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(14),
        'status'       => SessionStatus::Scheduled,
    ]);

    Livewire::actingAs($admin)
        ->test(RegistrationsManager::class, ['table' => $table])
        ->set('removeRegistrationId', $reg->id)
        ->set('removeChoice', 'reorient')
        ->set('removeTargetTableId', $otherTable->id)
        ->call('confirmRemove');

    expect($reg->fresh()->conversation_table_id)->toBe($otherTable->id);
});

it('confirmRemove with "pool" choice calls MoveToGlobalWaitlistAction', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeManagerSetup();

    $card = Card::factory()->create(['user_id' => $user->id]);
    $reg  = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    Livewire::actingAs($admin)
        ->test(RegistrationsManager::class, ['table' => $table])
        ->set('removeRegistrationId', $reg->id)
        ->set('removeChoice', 'pool')
        ->set('removeAdminReason', 'Annulation confirmée par l\'admin.')
        ->set('removeRecreditCard', true)
        ->call('confirmRemove');

    expect(GlobalWaitlistEntry::where('user_id', $user->id)
        ->where('status', GlobalWaitlistEntryStatus::Pending)
        ->exists()
    )->toBeTrue();
    expect($reg->fresh()->status)->toBe(RegistrationStatus::Cancelled);
});

it('pool route requires admin_reason when registration is Registered', function () {
    ['admin' => $admin, 'user' => $user, 'table' => $table] = makeManagerSetup();

    $card = Card::factory()->create(['user_id' => $user->id]);
    $reg  = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'card_id'               => $card->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    // Without admin reason → action should fail (RuntimeException caught, no pool entry)
    Livewire::actingAs($admin)
        ->test(RegistrationsManager::class, ['table' => $table])
        ->set('removeRegistrationId', $reg->id)
        ->set('removeChoice', 'pool')
        ->set('removeAdminReason', '')
        ->call('confirmRemove');

    expect(GlobalWaitlistEntry::where('user_id', $user->id)->exists())->toBeFalse();
    expect($reg->fresh()->status)->toBe(RegistrationStatus::Registered);
});
