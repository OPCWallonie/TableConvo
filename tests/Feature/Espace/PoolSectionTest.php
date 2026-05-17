<?php

use App\Actions\GlobalWaitlist\DismissGlobalWaitlistEntryAction;
use App\Enums\GlobalWaitlistEntryStatus;
use App\Livewire\Espace\DismissPoolButton;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makePoolSectionSetup(): array
{
    $level = Level::factory()->create(['code' => 'B1', 'sort_order' => 3]);
    $admin = User::factory()->create();
    $user  = User::factory()->create(['level_id' => $level->id]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    return compact('level', 'admin', 'user', 'entry');
}

// ─── Tests ──────────────────────────────────────────────────

it('user sees their pending pool entries on the inscriptions page', function () {
    ['user' => $user, 'entry' => $entry, 'level' => $level] = makePoolSectionSetup();

    $this->actingAs($user)
        ->get(route('espace.inscriptions'))
        ->assertOk()
        ->assertSee('vivier')
        ->assertSee($level->code);
});

it('pool section is hidden when user has no pending entries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('espace.inscriptions'))
        ->assertOk()
        ->assertDontSee('vivier');
});

it('user cannot see pool entries belonging to another user', function () {
    ['entry' => $entry, 'level' => $level] = makePoolSectionSetup();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('espace.inscriptions'))
        ->assertOk()
        ->assertDontSee('vivier');
});

it('user can dismiss their own pool entry via the Livewire component', function () {
    ['user' => $user, 'entry' => $entry] = makePoolSectionSetup();

    Livewire::actingAs($user)
        ->test(DismissPoolButton::class, ['entryId' => $entry->id])
        ->call('openDismissDialog', $entry->id)
        ->assertSet('showDialog', true)
        ->set('reason', 'Je change mes plans')
        ->call('confirmDismiss')
        ->assertRedirect(route('espace.inscriptions'));

    expect($entry->fresh()->status)->toBe(GlobalWaitlistEntryStatus::Dismissed);
    expect($entry->fresh()->dismissed_reason)->toBe('Je change mes plans');
});

it('user cannot dismiss another user\'s pool entry', function () {
    ['entry' => $entry] = makePoolSectionSetup();
    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test(DismissPoolButton::class, ['entryId' => $entry->id])
        ->call('openDismissDialog', $entry->id)
        ->set('reason', 'Tentative non autorisée')
        ->call('confirmDismiss')
        ->assertHasErrors(['general']);

    expect($entry->fresh()->status)->toBe(GlobalWaitlistEntryStatus::Pending);
});
