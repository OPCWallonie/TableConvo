<?php

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Enums\SessionStatus;
use App\Filament\Resources\GlobalWaitlistPool\GlobalWaitlistPoolResource;
use App\Filament\Resources\GlobalWaitlistPool\Pages\ListGlobalWaitlistPool;
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

function makePoolAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makePoolEntry(User $admin, ?Level $level = null): GlobalWaitlistEntry
{
    $level ??= Level::factory()->create(['code' => 'B1', 'sort_order' => 3]);
    $user   = User::factory()->create(['level_id' => $level->id]);
    return GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);
}

it('admin can list pool entries', function () {
    $admin = makePoolAdmin();
    makePoolEntry($admin);

    $this->actingAs($admin)->get(GlobalWaitlistPoolResource::getUrl('index'))->assertSuccessful();
});

it('non-admin cannot access the pool resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(GlobalWaitlistPoolResource::getUrl('index'))->assertForbidden();
});

it('shows only pending entries', function () {
    $admin = makePoolAdmin();

    $pending   = makePoolEntry($admin);
    $dismissed = GlobalWaitlistEntry::factory()->dismissed()->create(['created_by' => $admin->id]);
    $reassigned = GlobalWaitlistEntry::factory()->reassigned()->create(['created_by' => $admin->id]);

    $component = Livewire::actingAs($admin)->test(ListGlobalWaitlistPool::class);
    $records   = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($pending->id);
    expect($records->pluck('id'))->not->toContain($dismissed->id);
    expect($records->pluck('id'))->not->toContain($reassigned->id);
});

it('filter by level works', function () {
    $admin  = makePoolAdmin();
    $levelA = Level::factory()->create(['code' => 'A1', 'sort_order' => 1]);
    $levelB = Level::factory()->create(['code' => 'C1', 'sort_order' => 5]);

    $entryA = makePoolEntry($admin, $levelA);
    $entryB = makePoolEntry($admin, $levelB);

    $component = Livewire::actingAs($admin)
        ->test(ListGlobalWaitlistPool::class)
        ->set('tableFilters.level_id.value', $levelA->id);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($entryA->id);
    expect($records->pluck('id'))->not->toContain($entryB->id);
});

it('filter by source works', function () {
    $admin = makePoolAdmin();

    $level1 = Level::factory()->create(['sort_order' => 1]);
    $user1  = User::factory()->create(['level_id' => $level1->id]);
    $entryRemoved = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user1->id,
        'level_id'   => $level1->id,
        'created_by' => $admin->id,
        'source'     => GlobalWaitlistSource::AdminRemovedWaitlist,
    ]);

    $level2 = Level::factory()->create(['sort_order' => 2]);
    $user2  = User::factory()->create(['level_id' => $level2->id]);
    $entryCancelled = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user2->id,
        'level_id'   => $level2->id,
        'created_by' => $admin->id,
        'source'     => GlobalWaitlistSource::AdminCancelledRegistration,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListGlobalWaitlistPool::class)
        ->set('tableFilters.source.value', GlobalWaitlistSource::AdminRemovedWaitlist->value);

    $records = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($entryRemoved->id);
    expect($records->pluck('id'))->not->toContain($entryCancelled->id);
});

it('reassign action creates registration and marks entry as Reassigned', function () {
    $admin = makePoolAdmin();
    $level = Level::factory()->create(['code' => 'B2', 'sort_order' => 4]);
    $user  = User::factory()->create(['level_id' => $level->id]);
    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    $target = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'max_participants' => 8,
        'scheduled_at'     => now()->addDays(7),
        'status'           => SessionStatus::Scheduled,
    ]);

    Livewire::actingAs($admin)
        ->test(ListGlobalWaitlistPool::class)
        ->callTableAction('reassign', $entry, ['target_table_id' => $target->id]);

    expect($entry->fresh()->status)->toBe(GlobalWaitlistEntryStatus::Reassigned);
    expect(Registration::where('user_id', $user->id)
        ->where('conversation_table_id', $target->id)
        ->exists()
    )->toBeTrue();
});

it('dismiss action marks entry as Dismissed with reason', function () {
    $admin = makePoolAdmin();
    $entry = makePoolEntry($admin);

    Livewire::actingAs($admin)
        ->test(ListGlobalWaitlistPool::class)
        ->callTableAction('dismiss', $entry, ['reason' => 'Plus de disponibilité.']);

    $fresh = $entry->fresh();
    expect($fresh->status)->toBe(GlobalWaitlistEntryStatus::Dismissed);
    expect($fresh->dismissed_reason)->toBe('Plus de disponibilité.');
});
