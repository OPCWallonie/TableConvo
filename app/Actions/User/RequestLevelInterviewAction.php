<?php

namespace App\Actions\User;

use App\Models\User;
use App\Settings\EmailSettings;
use Illuminate\Support\Facades\Mail;

class RequestLevelInterviewAction
{
    public function __construct(private readonly EmailSettings $emailSettings) {}

    public function execute(User $user): void
    {
        $adminEmail = $this->emailSettings->admin_notifications_email;
        if (empty($adminEmail)) {
            return;
        }

        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Demande d\'entretien de niveau déclenchée (premier essai d\'inscription sans niveau)');
    }
}
