<?php

use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\Level;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// 1. Page accessible
// ─────────────────────────────────────────────────────────────

it('agenda page is accessible and returns required view data', function () {
    $response = $this->get(route('agenda'));

    $response->assertOk();
    $response->assertViewIs('public.agenda.index');
    $response->assertViewHas('tables');
    $response->assertViewHas('levels');
    $response->assertViewHas('levelCode');
    expect($response->viewData('levelCode'))->toBeNull();
});

// ─────────────────────────────────────────────────────────────
// 2. Filtre par code de niveau
// ─────────────────────────────────────────────────────────────

it('level filter returns only tables matching the given CECRL code', function () {
    $a2 = Level::factory()->withCode('A2')->create();
    $b1 = Level::factory()->withCode('B1')->create();

    ConversationTable::factory()->create([
        'level_id'     => $a2->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);
    ConversationTable::factory()->create([
        'level_id'     => $b1->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);

    // Filtre A2 → une seule table
    $response = $this->get(route('agenda', ['level' => 'A2']));
    $response->assertOk();

    $tables = $response->viewData('tables');
    expect($tables->total())->toBe(1);
    expect($tables->first()->level->code)->toBe('A2');
    expect($response->viewData('levelCode'))->toBe('A2');
});

it('invalid level code silently falls back to showing all tables', function () {
    $level = Level::factory()->withCode('C2')->create();
    ConversationTable::factory()->count(2)->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(5),
    ]);

    $response = $this->get(route('agenda', ['level' => 'INVALID']));

    $response->assertOk();
    expect($response->viewData('tables')->total())->toBe(2);
});

// ─────────────────────────────────────────────────────────────
// 3. Pagination et cohérence de la query
// ─────────────────────────────────────────────────────────────

it('agenda returns a paginator, excludes past and cancelled sessions, and orders by date asc', function () {
    $level = Level::factory()->withCode('B2')->create();

    // Deux sessions futures à venir (à conserver)
    $first  = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(3),
    ]);
    $second = ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->addDays(10),
    ]);

    // Session passée → exclue
    ConversationTable::factory()->create([
        'level_id'     => $level->id,
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => now()->subDay(),
    ]);

    // Session annulée → exclue
    ConversationTable::factory()->cancelled()->create([
        'level_id'     => $level->id,
        'scheduled_at' => now()->addDays(7),
    ]);

    $response = $this->get(route('agenda'));
    $response->assertOk();

    $tables = $response->viewData('tables');

    expect($tables)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($tables->total())->toBe(2);
    expect($tables->first()->id)->toBe($first->id);   // ordre chronologique
    expect($tables->last()->id)->toBe($second->id);
});
