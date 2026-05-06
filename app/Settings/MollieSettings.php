<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MollieSettings extends Settings
{
    public string $api_key = '';
    public bool $test_mode = true;
    public string $webhook_secret = '';

    public static function group(): string
    {
        return 'mollie';
    }

    public static function encrypted(): array
    {
        return ['api_key', 'webhook_secret'];
    }
}
