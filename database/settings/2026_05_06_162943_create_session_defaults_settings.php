<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('session_defaults.default_duration_minutes', 90);
        $this->migrator->add('session_defaults.default_location', '');
        $this->migrator->add('session_defaults.default_max_participants', 8);
    }
};
