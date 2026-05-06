<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('email.from_email', '');
        $this->migrator->add('email.from_name', 'TableConvo');
        $this->migrator->add('email.reply_to', '');
        $this->migrator->add('email.admin_notifications_email', '');
        $this->migrator->add('email.notifications_enabled', []);
    }
};
