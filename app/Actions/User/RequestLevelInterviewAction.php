<?php

namespace App\Actions\User;

use App\Models\User;
use App\Notifications\NotifyAdminOfLevelInterviewNeeded;

class RequestLevelInterviewAction
{
    public function execute(User $user): void
    {
        // Gating : ne notifie l'admin qu'une seule fois par utilisateur
        if ($user->interview_requested_at !== null) {
            return;
        }

        $user->update(['interview_requested_at' => now()]);

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log("Demande d'entretien de niveau — première tentative d'inscription sans niveau");

        // Notifie tous les admins (rôle 'admin') pour assertSentTo testable
        User::role('admin')->get()->each(
            fn (User $admin) => $admin->notify(new NotifyAdminOfLevelInterviewNeeded($user))
        );
    }
}
