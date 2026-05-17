<?php

use App\Enums\GlobalWaitlistEntryStatus;
use App\Enums\GlobalWaitlistSource;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Factory & hydratation
// ─────────────────────────────────────────────────────────────

it('can be created via factory with default pending status', function () {
    $entry = GlobalWaitlistEntry::factory()->create();

    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Pending);
    expect($entry->source)->toBeInstanceOf(GlobalWaitlistSource::class);
    expect($entry->requested_at)->not->toBeNull();
});

it('factory pending() state produces a pending entry', function () {
    $entry = GlobalWaitlistEntry::factory()->pending()->create();

    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Pending);
});

it('factory reassigned() state produces a reassigned entry', function () {
    $entry = GlobalWaitlistEntry::factory()->reassigned()->create();

    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Reassigned);
});

it('factory dismissed() state produces a dismissed entry with reason and timestamp', function () {
    $entry = GlobalWaitlistEntry::factory()->dismissed()->create();

    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Dismissed);
    expect($entry->dismissed_reason)->not->toBeNull();
    expect($entry->dismissed_at)->not->toBeNull();
});

it('factory fromCancellation() state sets admin_reason and correct source', function () {
    $entry = GlobalWaitlistEntry::factory()->fromCancellation()->create();

    expect($entry->source)->toBe(GlobalWaitlistSource::AdminCancelledRegistration);
    expect($entry->admin_reason)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────
// Casts
// ─────────────────────────────────────────────────────────────

it('casts status to GlobalWaitlistEntryStatus enum', function () {
    $entry = GlobalWaitlistEntry::factory()->create(['status' => 'pending']);

    expect($entry->status)->toBe(GlobalWaitlistEntryStatus::Pending);
});

it('casts source to GlobalWaitlistSource enum', function () {
    $entry = GlobalWaitlistEntry::factory()->create([
        'source' => 'admin_removed_waitlist',
    ]);

    expect($entry->source)->toBe(GlobalWaitlistSource::AdminRemovedWaitlist);
});

it('casts requested_at and dismissed_at to Carbon instances', function () {
    $entry = GlobalWaitlistEntry::factory()->dismissed()->create([
        'requested_at' => now()->subDays(5),
    ]);

    expect($entry->requested_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($entry->dismissed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// ─────────────────────────────────────────────────────────────
// Scopes
// ─────────────────────────────────────────────────────────────

it('scope pending() returns only pending entries', function () {
    GlobalWaitlistEntry::factory()->pending()->create();
    GlobalWaitlistEntry::factory()->reassigned()->create();
    GlobalWaitlistEntry::factory()->dismissed()->create();

    $results = GlobalWaitlistEntry::pending()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->status)->toBe(GlobalWaitlistEntryStatus::Pending);
});

it('scope forLevel() filters by level', function () {
    $levelA = Level::factory()->create();
    $levelB = Level::factory()->create();

    $admin = User::factory()->create();

    GlobalWaitlistEntry::factory()->create(['level_id' => $levelA->id, 'created_by' => $admin->id]);
    GlobalWaitlistEntry::factory()->create(['level_id' => $levelB->id, 'created_by' => $admin->id]);

    $results = GlobalWaitlistEntry::forLevel($levelA)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->level_id)->toBe($levelA->id);
});

it('scope oldestFirst() orders entries by requested_at ascending', function () {
    $admin = User::factory()->create();

    $older = GlobalWaitlistEntry::factory()->create([
        'requested_at' => now()->subDays(10),
        'created_by'   => $admin->id,
    ]);
    $newer = GlobalWaitlistEntry::factory()->create([
        'requested_at' => now()->subDays(1),
        'created_by'   => $admin->id,
    ]);

    $results = GlobalWaitlistEntry::oldestFirst()->get();

    expect($results->first()->id)->toBe($older->id);
    expect($results->last()->id)->toBe($newer->id);
});

// ─────────────────────────────────────────────────────────────
// Accesseur waitingDays
// ─────────────────────────────────────────────────────────────

it('waitingDays accessor returns correct number of days', function () {
    $entry = GlobalWaitlistEntry::factory()->waitingDays(7)->create();

    expect($entry->waitingDays)->toBe(7);
});

it('waitingDays accessor returns 0 for an entry created today', function () {
    $entry = GlobalWaitlistEntry::factory()->create(['requested_at' => now()]);

    expect($entry->waitingDays)->toBe(0);
});

// ─────────────────────────────────────────────────────────────
// Relations
// ─────────────────────────────────────────────────────────────

it('belongs to a user', function () {
    $user  = User::factory()->create();
    $admin = User::factory()->create();
    $level = Level::factory()->create();

    $entry = GlobalWaitlistEntry::factory()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    expect($entry->user->id)->toBe($user->id);
});

it('belongs to a level', function () {
    $level = Level::factory()->create();
    $admin = User::factory()->create();

    $entry = GlobalWaitlistEntry::factory()->create([
        'level_id'   => $level->id,
        'created_by' => $admin->id,
    ]);

    expect($entry->level->id)->toBe($level->id);
});

it('belongs to the admin who created it via createdBy relation', function () {
    $admin = User::factory()->create();

    $entry = GlobalWaitlistEntry::factory()->create(['created_by' => $admin->id]);

    expect($entry->createdBy->id)->toBe($admin->id);
});

// ─────────────────────────────────────────────────────────────
// Soft deletes
// ─────────────────────────────────────────────────────────────

it('supports soft deletes', function () {
    $entry = GlobalWaitlistEntry::factory()->create();
    $id    = $entry->id;

    $entry->delete();

    expect(GlobalWaitlistEntry::find($id))->toBeNull();
    expect(GlobalWaitlistEntry::withTrashed()->find($id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────
// ActivityLog configuré
// ─────────────────────────────────────────────────────────────

it('logs a status change via Spatie ActivityLog', function () {
    $entry = GlobalWaitlistEntry::factory()->pending()->create();

    $initialCount = \Spatie\Activitylog\Models\Activity::forSubject($entry)->count();

    $entry->update(['status' => GlobalWaitlistEntryStatus::Dismissed]);

    $logs = \Spatie\Activitylog\Models\Activity::forSubject($entry)->orderBy('id')->get();

    // Au moins un log supplémentaire doit exister après la mise à jour
    expect($logs->count())->toBeGreaterThan($initialCount);

    // Le log le plus récent doit porter la nouvelle valeur de status
    // Les événements modèle auto-loggés vont dans attribute_changes (pas properties)
    $updatedLog = $logs->last();
    expect(data_get($updatedLog->attribute_changes, 'attributes.status'))->toBe('dismissed');
});
