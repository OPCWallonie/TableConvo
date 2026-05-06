<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class PromoteFromWaitlistAction
{
    public function execute(Registration $registration, User $admin): Registration
    {
        if ($registration->status !== RegistrationStatus::Waitlist) {
            throw new RuntimeException('registration_not_on_waitlist');
        }

        $table = $registration->conversationTable;
        if ($table->isFull()) {
            throw new RuntimeException('table_still_full');
        }

        return DB::transaction(function () use ($registration, $admin) {
            $card = $registration->user->activeCards()->first();
            if (! $card || ! $card->hasSessionsRemaining()) {
                throw new RuntimeException('no_active_card_for_promotion');
            }

            $card->decrement('sessions_remaining');

            $registration->update([
                'status' => RegistrationStatus::Registered,
                'card_id' => $card->id,
                'waitlist_position' => null,
            ]);

            activity()
                ->performedOn($registration)
                ->causedBy($admin)
                ->log('Promu depuis la liste d\'attente');

            return $registration->fresh();
        });
    }
}
