<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Registered = 'registered';
    case Waitlist = 'waitlist';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match($this) {
            self::Registered => 'Inscrit',
            self::Waitlist => 'Liste d\'attente',
            self::Cancelled => 'Annulé',
            self::Attended => 'Présent',
            self::NoShow => 'Absent',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Registered => 'success',
            self::Waitlist => 'warning',
            self::Cancelled => 'danger',
            self::Attended => 'info',
            self::NoShow => 'gray',
        };
    }
}
