<?php

namespace App\Enums;

enum GlobalWaitlistSource: string
{
    case AdminRemovedWaitlist       = 'admin_removed_waitlist';
    case AdminCancelledRegistration = 'admin_cancelled_registration';
    case UserVolunteer              = 'user_volunteer';

    public function label(): string
    {
        return match ($this) {
            self::AdminRemovedWaitlist       => 'Retrait liste d\'attente',
            self::AdminCancelledRegistration => 'Annulation inscription',
            self::UserVolunteer              => 'Inscription volontaire',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AdminRemovedWaitlist       => 'warning',
            self::AdminCancelledRegistration => 'danger',
            self::UserVolunteer              => 'info',
        };
    }
}
