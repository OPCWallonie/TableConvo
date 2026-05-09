<?php

use App\Enums\RegistrationStatus;
use App\Filament\Widgets\NoShowRateWidget;
use App\Models\ConversationTable;
use App\Models\Registration;

function makeNoShowWidget(): NoShowRateWidget
{
    return new class extends NoShowRateWidget {
        public function getStatsPublic(): array
        {
            return $this->getStats();
        }
    };
}

it('computes no-show rate correctly with mixed registrations', function () {
    $session = ConversationTable::factory()->create([
        'scheduled_at' => now()->subDays(5),
    ]);

    Registration::factory()->count(2)->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::Attended,
    ]);
    Registration::factory()->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::NoShow,
    ]);

    $stats = makeNoShowWidget()->getStatsPublic();

    expect($stats[0]->getValue())->toBe('33.3 %');
});

it('returns 0 if no registrations in last 30 days', function () {
    $oldSession = ConversationTable::factory()->create([
        'scheduled_at' => now()->subDays(40),
    ]);
    Registration::factory()->create([
        'conversation_table_id' => $oldSession->id,
        'status'                => RegistrationStatus::NoShow,
    ]);

    $stats = makeNoShowWidget()->getStatsPublic();

    expect($stats[0]->getValue())->toBe('0 %');
});

it('applies correct color thresholds (green / orange / red)', function () {
    $session = ConversationTable::factory()->create(['scheduled_at' => now()->subDays(5)]);

    // Phase 1: 15 attended + 1 no-show = 16 total → 6.25% → success
    Registration::factory()->count(15)->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::Attended,
    ]);
    Registration::factory()->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::NoShow,
    ]);

    $statsGreen = makeNoShowWidget()->getStatsPublic();
    expect($statsGreen[0]->getColor())->toBe('success');

    // Phase 2: add 2 more no-shows → 3/18 total = 16.67% → warning
    Registration::factory()->count(2)->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::NoShow,
    ]);

    $statsWarning = makeNoShowWidget()->getStatsPublic();
    expect($statsWarning[0]->getColor())->toBe('warning');

    // Phase 3: add 3 more no-shows → 6/21 total = 28.57% → danger
    Registration::factory()->count(3)->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::NoShow,
    ]);

    $statsDanger = makeNoShowWidget()->getStatsPublic();
    expect($statsDanger[0]->getColor())->toBe('danger');
});
