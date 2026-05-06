<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mollie.api_key', '');
        $this->migrator->add('mollie.test_mode', true);
        $this->migrator->add('mollie.webhook_secret', '');
    }
};
