<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SessionDefaultsSettings extends Settings
{
    public int $default_duration_minutes = 90;
    public string $default_location = '';
    public int $default_max_participants = 8;

    public static function group(): string
    {
        return 'session_defaults';
    }
}
