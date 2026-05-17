<?php

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\SessionStatus;
use App\Filament\Widgets\GlobalWaitlistWidget;
use App\Models\Card;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeWidgetAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    return $admin;
}

function makeWidgetEntry(User $admin, int $daysAgo = 0): GlobalWaitlistEntry
{
    $level = Level::factory()->create(['sort_order' => rand(1, 10)]);
    $user  = User::factory()->create(['level_id' => $level->id]);
    return GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'      => $user->id,
        'level_id'     => $level->id,
        'created_by'   => $admin->id,
        'requested_at' => now()->subDays($daysAgo),
    ]);
}

it('widget renders for admin', function () {
    $admin = makeWidgetAdmin();

    Livewire::actingAs($admin)
        ->test(GlobalWaitlistWidget::class)
        ->assertSuccessful();
});

it('widget is hidden for non-admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(GlobalWaitlistWidget::canView())->toBeFalse();
});

it('shows only pending entries', function () {
    $admin = makeWidgetAdmin();

    $pending   = makeWidgetEntry($admin);
    $dismissed = GlobalWaitlistEntry::factory()->dismissed()->create(['created_by' => $admin->id]);

    $component = Livewire::actingAs($admin)->test(GlobalWaitlistWidget::class);
    $records   = $component->instance()->getTable()->getRecords();

    expect($records->pluck('id'))->toContain($pending->id);
    expect($records->pluck('id'))->not->toContain($dismissed->id);
});

it('shows max 10 entries', function () {
    $admin = makeWidgetAdmin();

    for ($i = 0; $i < 12; $i++) {
        makeWidgetEntry($admin, $i);
    }

    $component = Livewire::actingAs($admin)->test(GlobalWaitlistWidget::class);
    $records   = $component->instance()->getTable()->getRecords();

    expect($records->count())->toBeLessThanOrEqual(10);
});

it('orders by requested_at ascending (FIFO)', function () {
    $admin = makeWidgetAdmin();

    $oldest = makeWidgetEntry($admin, 10);
    $newest = makeWidgetEntry($admin, 2);

    $component = Livewire::actingAs($admin)->test(GlobalWaitlistWidget::class);
    $records   = $component->instance()->getTable()->getRecords();

    expect($records->first()->id)->toBe($oldest->id);
    expect($records->last()->id)->toBe($newest->id);
});

it('empty state renders when no pending entries', function () {
    $admin = makeWidgetAdmin();

    Livewire::actingAs($admin)
        ->test(GlobalWaitlistWidget::class)
        ->assertSuccessful();

    $component = Livewire::actingAs($admin)->test(GlobalWaitlistWidget::class);
    $records   = $component->instance()->getTable()->getRecords();

    expect($records->count())->toBe(0);
});

it('waiting_days warning color triggers at 14+ days', function () {
    $admin = makeWidgetAdmin();
    $level = Level::factory()->create(['sort_order' => 1]);
    $user  = User::factory()->create(['level_id' => $level->id]);

    GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'      => $user->id,
        'level_id'     => $level->id,
        'created_by'   => $admin->id,
        'requested_at' => now()->subDays(14),
    ]);

    $entry = GlobalWaitlistEntry::latest('id')->first();
    expect($entry->waitingDays)->toBe(14);
});

it('waiting_days danger color triggers at 30+ days', function () {
    $admin = makeWidgetAdmin();
    $level = Level::factory()->create(['sort_order' => 1]);
    $user  = User::factory()->create(['level_id' => $level->id]);

    GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'      => $user->id,
        'level_id'     => $level->id,
        'created_by'   => $admin->id,
        'requested_at' => now()->subDays(31),
    ]);

    $entry = GlobalWaitlistEntry::latest('id')->first();
    expect($entry->waitingDays)->toBeGreaterThanOrEqual(30);
});

it('reassign action creates registration from widget', function () {
    $admin = makeWidgetAdmin();
    $level = Level::factory()->create(['code' => 'A2', 'sort_order' => 2]);
    $user  = User::factory()->create(['level_id' => $level->id]);
    Card::factory()->create(['user_id' => $user->id, 'sessions_remaining' => 5]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    $target = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'max_participants' => 8,
        'scheduled_at' => now()->addDays(7),
        'status'       => SessionStatus::Scheduled,
    ]);

    Livewire::actingAs($admin)
        ->test(GlobalWaitlistWidget::class)
        ->callTableAction('reassign', $entry, ['target_table_id' => $target->id]);

    expect($entry->fresh()->status)->toBe(GlobalWaitlistEntryStatus::Reassigned);
});
