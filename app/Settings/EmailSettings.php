<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EmailSettings extends Settings
{
    public string $from_email = '';
    public string $from_name = 'TableConvo';
    public string $reply_to = '';
    public string $admin_notifications_email = '';
    public array $notifications_enabled = [];

    public static function group(): string
    {
        return 'email';
    }
}
