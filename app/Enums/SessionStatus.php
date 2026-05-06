<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Planifiée',
            self::Cancelled => 'Annulée',
            self::Completed => 'Terminée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Scheduled => 'info',
            self::Cancelled => 'danger',
            self::Completed => 'success',
        };
    }
}
