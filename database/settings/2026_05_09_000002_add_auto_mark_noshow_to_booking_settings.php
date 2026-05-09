<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.auto_mark_noshow_after_days', 7);
    }

    public function down(): void
    {
        $this->migrator->delete('booking.auto_mark_noshow_after_days');
    }
};
