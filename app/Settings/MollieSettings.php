<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MollieSettings extends Settings
{
    public ?string $api_key = null;
    public bool $test_mode = true;
    public ?string $webhook_secret = null;

    public static function group(): string
    {
        return 'mollie';
    }

    public static function encrypted(): array
    {
        return ['api_key', 'webhook_secret'];
    }
}
