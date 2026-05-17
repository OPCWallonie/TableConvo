<?php

namespace App\Enums;

enum GlobalWaitlistEntryStatus: string
{
    case Pending    = 'pending';
    case Reassigned = 'reassigned';
    case Dismissed  = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'En attente',
            self::Reassigned => 'Réassigné(e)',
            self::Dismissed  => 'Retiré(e)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::Reassigned => 'success',
            self::Dismissed  => 'gray',
        };
    }
}
