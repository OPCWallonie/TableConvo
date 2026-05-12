<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Collection;

class FindEligibleTargetSessionsAction
{
    public function execute(Registration $registration): Collection
    {
        return \App\Models\ConversationTable::query()
            ->where('status', SessionStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->where('level_id', $registration->conversationTable->level_id)
            ->where('id', '!=', $registration->conversation_table_id)
            ->withCount([
                'registrations as registered_count' => fn ($q) =>
                    $q->where('status', RegistrationStatus::Registered->value),
                'registrations as waitlist_count' => fn ($q) =>
                    $q->where('status', RegistrationStatus::Waitlist->value),
            ])
            ->orderBy('scheduled_at')
            ->get();
    }
}
