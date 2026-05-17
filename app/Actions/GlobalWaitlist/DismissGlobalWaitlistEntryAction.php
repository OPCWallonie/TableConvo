<?php

namespace App\Actions\GlobalWaitlist;

use App\Enums\GlobalWaitlistEntryStatus;
use App\Models\GlobalWaitlistEntry;
use App\Models\User;
use App\Notifications\DismissedFromGlobalPoolNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DismissGlobalWaitlistEntryAction
{
    /**
     * Écarte une entrée du vivier global (admin ou retrait volontaire utilisateur).
     *
     * La notification DismissedFromGlobalPoolNotification n'est envoyée que si l'action
     * est déclenchée par un admin (byUser = false).
     *
     * @param  GlobalWaitlistEntry $entry   Entrée vivier à écarter (doit être Pending).
     * @param  User                $actor   Admin ou utilisateur déclencheur.
     * @param  string              $reason  Raison de l'écartement (non-vide).
     * @param  bool                $byUser  True si l'utilisateur se retire lui-même.
     * @return GlobalWaitlistEntry Entrée mise à jour avec statut Dismissed.
     * @throws RuntimeException entry_not_pending — entrée déjà traitée.
     * @throws RuntimeException dismiss_reason_required — raison vide ou absente.
     * @throws RuntimeException unauthorized_dismiss — utilisateur tente de retirer une entrée qui n'est pas la sienne.
     */
    public function execute(
        GlobalWaitlistEntry $entry,
        User $actor,
        string $reason,
        bool $byUser = false,
    ): GlobalWaitlistEntry {
        if ($entry->status !== GlobalWaitlistEntryStatus::Pending) {
            throw new RuntimeException('entry_not_pending');
        }

        if (blank(trim($reason))) {
            throw new RuntimeException('dismiss_reason_required');
        }

        if ($byUser && $actor->id !== $entry->user_id) {
            throw new RuntimeException('unauthorized_dismiss');
        }

        return DB::transaction(function () use ($entry, $actor, $reason, $byUser) {
            $entry->update([
                'status'           => GlobalWaitlistEntryStatus::Dismissed,
                'dismissed_reason' => $reason,
                'dismissed_at'     => now(),
                'dismissed_by'     => $actor->id,
            ]);

            activity()
                ->performedOn($entry)
                ->causedBy($actor)
                ->log($byUser ? 'Retrait volontaire du vivier' : 'Retrait du vivier par admin');

            if (! $byUser) {
                DB::afterCommit(function () use ($entry) {
                    $entry->user->notify(new DismissedFromGlobalPoolNotification($entry->fresh()));
                });
            }

            return $entry->fresh();
        });
    }
}
