<?php

namespace App\Enums;

enum CardStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Expired => 'Expirée',
            self::Refunded => 'Remboursée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active => 'success',
            self::Expired => 'gray',
            self::Refunded => 'warning',
        };
    }
}
