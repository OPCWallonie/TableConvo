<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CardSettings extends Settings
{
    public int $default_validity_months = 12;
    public int $default_sessions_count = 10;
    public float $default_price_per_card = 250.00;
    public array $expiration_warning_days = [30, 7];

    public static function group(): string
    {
        return 'card';
    }
}
