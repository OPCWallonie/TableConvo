<?php

namespace App\Actions\Session;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkAttendanceAction
{
    public function execute(ConversationTable $table, array $attendedUserIds, User $admin): void
    {
        DB::transaction(function () use ($table, $attendedUserIds, $admin) {
            $registrations = $table->registrations()
                ->where('status', RegistrationStatus::Registered->value)
                ->get();

            foreach ($registrations as $registration) {
                $status = in_array($registration->user_id, $attendedUserIds)
                    ? RegistrationStatus::Attended
                    : RegistrationStatus::NoShow;

                $registration->update(['status' => $status]);
            }

            $table->update(['status' => SessionStatus::Completed]);

            activity()
                ->performedOn($table)
                ->causedBy($admin)
                ->log('Présences enregistrées');
        });
    }
}
