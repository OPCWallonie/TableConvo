<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Models\ConversationTable;
use App\Models\Registration;
use App\Models\User;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class MoveRegistrationAction
{
    public function execute(Registration $registration, ConversationTable $newTable, User $admin): Registration
    {
        if ($registration->status === RegistrationStatus::Cancelled) {
            throw new RuntimeException('cannot_move_cancelled_registration');
        }

        return DB::transaction(function () use ($registration, $newTable, $admin) {
            $oldTable = $registration->conversationTable;

            $registration->update([
                'conversation_table_id' => $newTable->id,
            ]);

            activity()
                ->performedOn($registration)
                ->causedBy($admin)
                ->withProperties(['from_table' => $oldTable->id, 'to_table' => $newTable->id])
                ->log("Inscription déplacée de la table #{$oldTable->id} vers #{$newTable->id}");

            return $registration->fresh();
        });
    }
}
