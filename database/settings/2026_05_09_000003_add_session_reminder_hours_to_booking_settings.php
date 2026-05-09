<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.session_reminder_hours_before', 24);
    }

    public function down(): void
    {
        $this->migrator->delete('booking.session_reminder_hours_before');
    }
};
