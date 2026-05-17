<?php

namespace App\Actions\GlobalWaitlist;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\GlobalWaitlistEntry;
use Illuminate\Database\Eloquent\Collection;

class FindCompatibleSessionsForGlobalEntryAction
{
    /**
     * Retourne les sessions compatibles avec une entrée vivier (même niveau, planifiées, futures).
     *
     * Contrairement à FindEligibleTargetSessionsAction, les sessions où l'utilisateur a déjà
     * une inscription ne sont pas filtrées — une entrée vivier n'est pas liée à une session.
     * Les sessions complètes sont incluses : l'admin peut réassigner en waitlist.
     *
     * @param  GlobalWaitlistEntry $entry Entrée vivier pour laquelle chercher des sessions.
     * @return Collection<int, \App\Models\ConversationTable> Sessions triées par date ASC.
     */
    public function execute(GlobalWaitlistEntry $entry): Collection
    {
        return \App\Models\ConversationTable::query()
            ->where('status', SessionStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->where('level_id', $entry->level_id)
            ->with('level')
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
