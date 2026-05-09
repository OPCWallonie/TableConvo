<?php

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Filament\Widgets\SessionFillRateChartWidget;
use App\Models\ConversationTable;
use App\Models\Registration;

function makeFillRateWidget(): SessionFillRateChartWidget
{
    return new class extends SessionFillRateChartWidget {
        public function getDataPublic(): array
        {
            return $this->getData();
        }
    };
}

it('computes fill rate per week correctly', function () {
    $weekStart = now()->startOfWeek();

    $session = ConversationTable::factory()->create([
        'status'           => SessionStatus::Completed,
        'scheduled_at'     => $weekStart->copy()->addHours(10),
        'max_participants' => 4,
    ]);

    Registration::factory()->count(2)->create([
        'conversation_table_id' => $session->id,
        'status'                => RegistrationStatus::Attended,
    ]);

    $data = makeFillRateWidget()->getDataPublic();

    expect($data['datasets'][0]['data'][11])->toBe(50.0);
});

it('ignores Cancelled and Scheduled sessions (only Completed counted)', function () {
    $weekStart = now()->startOfWeek();

    ConversationTable::factory()->create([
        'status'       => SessionStatus::Scheduled,
        'scheduled_at' => $weekStart->copy()->addHours(10),
    ]);
    ConversationTable::factory()->create([
        'status'       => SessionStatus::Cancelled,
        'scheduled_at' => $weekStart->copy()->addHours(10),
    ]);

    $data = makeFillRateWidget()->getDataPublic();

    expect($data['datasets'][0]['data'][11])->toBe(0);
});

it('handles weeks with no sessions (returns 0 gracefully)', function () {
    $data = makeFillRateWidget()->getDataPublic();

    expect($data['datasets'][0]['data'])->toHaveCount(12)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(0);
});
