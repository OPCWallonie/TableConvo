<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationRedirectedNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MoveRegistrationAction
{
    public function execute(Registration $registration, ConversationTable $newTable, User $admin, ?string $context = null): Registration
    {
        if ($registration->status === RegistrationStatus::Cancelled) {
            throw new RuntimeException('cannot_move_cancelled_registration');
        }

        if ($newTable->status !== SessionStatus::Scheduled) {
            throw new RuntimeException('target_table_not_scheduled');
        }

        if ($newTable->scheduled_at->isPast()) {
            throw new RuntimeException('target_table_in_past');
        }

        $alreadyOnTarget = $newTable->registrations()
            ->where('user_id', $registration->user_id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->exists();

        if ($alreadyOnTarget) {
            throw new RuntimeException('user_already_on_target_table');
        }

        if ($registration->status === RegistrationStatus::Registered && $newTable->isFull()) {
            throw new RuntimeException('target_table_full');
        }

        return DB::transaction(function () use ($registration, $newTable, $admin, $context) {
            $oldTable    = $registration->conversationTable;
            $oldPosition = $registration->waitlist_position;
            $isWaitlist  = $registration->status === RegistrationStatus::Waitlist;

            $newPosition = null;
            if ($isWaitlist) {
                $newPosition = ($newTable->registrations()
                    ->where('status', RegistrationStatus::Waitlist->value)
                    ->max('waitlist_position') ?? 0) + 1;
            }

            $registration->update([
                'conversation_table_id' => $newTable->id,
                'waitlist_position'     => $newPosition,
            ]);

            // Décalage FIFO sur l'ancienne table après le départ d'un waitlister
            if ($isWaitlist && $oldPosition !== null) {
                $oldTable->registrations()
                    ->where('status', RegistrationStatus::Waitlist->value)
                    ->where('waitlist_position', '>', $oldPosition)
                    ->decrement('waitlist_position');
            }

            activity()
                ->performedOn($registration)
                ->causedBy($admin)
                ->withProperties(['from_table' => $oldTable->id, 'to_table' => $newTable->id])
                ->log("Inscription déplacée de la table #{$oldTable->id} vers #{$newTable->id}");

            $fresh = $registration->fresh();

            if ($context === 'admin_redirect' && $isWaitlist) {
                DB::afterCommit(function () use ($oldTable, $fresh) {
                    $fresh->user->notify(new RegistrationRedirectedNotification($oldTable, $fresh));
                });
            }

            return $fresh;
        });
    }
}
