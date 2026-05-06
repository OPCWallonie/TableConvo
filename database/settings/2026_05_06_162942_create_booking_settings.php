<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('booking.registration_deadline_hours', 24);
        $this->migrator->add('booking.cancellation_deadline_business_days', 3);
        $this->migrator->add('booking.max_registrations_per_week', 1);
        $this->migrator->add('booking.max_future_registrations', 3);
        $this->migrator->add('booking.post_cancellation_card_extension_days', 30);
        $this->migrator->add('booking.post_cancellation_extension_threshold_days', 30);
        $this->migrator->add('booking.waitlist_auto_promote', false);
    }
};
