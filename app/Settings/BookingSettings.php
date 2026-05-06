<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BookingSettings extends Settings
{
    public int $registration_deadline_hours = 24;
    public int $cancellation_deadline_business_days = 3;
    public int $max_registrations_per_week = 1;
    public int $max_future_registrations = 3;
    public int $post_cancellation_card_extension_days = 30;
    public int $post_cancellation_extension_threshold_days = 30;
    public bool $waitlist_auto_promote = false;

    public static function group(): string
    {
        return 'booking';
    }
}
