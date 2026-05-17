<?php

use App\Actions\GlobalWaitlist\FindCompatibleSessionsForGlobalEntryAction;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeFindCompatibleSetup(): array
{
    $admin = User::factory()->create();
    $level = Level::factory()->create(['code' => 'A2', 'sort_order' => 2]);
    $user  = User::factory()->create(['level_id' => $level->id]);
    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'level', 'user', 'entry');
}

// ─── Tests ──────────────────────────────────────────────────

it('returns only scheduled future sessions matching the level', function () {
    ['level' => $level, 'entry' => $entry] = makeFindCompatibleSetup();

    $matching = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(5),
        'status'       => SessionStatus::Scheduled,
    ]);

    $otherLevel = Level::factory()->create(['code' => 'C2', 'sort_order' => 6]);
    ConversationTable::factory()->create([
        'level_id'     => $otherLevel->id,
        'scheduled_at' => now()->addDays(5),
        'status'       => SessionStatus::Scheduled,
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

it('excludes past sessions', function () {
    ['level' => $level, 'entry' => $entry] = makeFindCompatibleSetup();

    ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->subDay(),
        'status'       => SessionStatus::Scheduled,
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(0);
});

it('excludes cancelled sessions', function () {
    ['level' => $level, 'entry' => $entry] = makeFindCompatibleSetup();

    ConversationTable::factory()->cancelled()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(5),
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(0);
});

it('excludes sessions of other levels', function () {
    ['entry' => $entry] = makeFindCompatibleSetup();

    $otherLevel = Level::factory()->create(['code' => 'C1', 'sort_order' => 5]);
    ConversationTable::factory()->create([
        'level_id'     => $otherLevel->id,
        'scheduled_at' => now()->addDays(5),
        'status'       => SessionStatus::Scheduled,
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(0);
});

it('orders by scheduled_at ascending', function () {
    ['level' => $level, 'entry' => $entry] = makeFindCompatibleSetup();

    $latest  = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(14),
        'status'       => SessionStatus::Scheduled,
    ]);
    $soonest = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(3),
        'status'       => SessionStatus::Scheduled,
    ]);
    $middle  = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(7),
        'status'       => SessionStatus::Scheduled,
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(3);
    expect($results->get(0)->id)->toBe($soonest->id);
    expect($results->get(1)->id)->toBe($middle->id);
    expect($results->get(2)->id)->toBe($latest->id);
});

it('includes full sessions (admin can still reassign as waitlist)', function () {
    ['level' => $level, 'entry' => $entry] = makeFindCompatibleSetup();

    $fullTable = ConversationTable::factory()->create([
        'level_id'         => $level->id,
        'scheduled_at'     => now()->addDays(5),
        'status'           => SessionStatus::Scheduled,
        'max_participants' => 1,
    ]);

    $occupant = User::factory()->create();
    Registration::factory()->create([
        'user_id'               => $occupant->id,
        'conversation_table_id' => $fullTable->id,
        'status'                => RegistrationStatus::Registered,
    ]);

    $results = app(FindCompatibleSessionsForGlobalEntryAction::class)->execute($entry);

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($fullTable->id);
    expect($results->first()->registered_count)->toBe(1);
});
