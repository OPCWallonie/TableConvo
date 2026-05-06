<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class FixMollieEncryptedNulls extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('mollie.api_key', fn () => null);
        $this->migrator->update('mollie.webhook_secret', fn () => null);
    }
}
