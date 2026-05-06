<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('card.default_validity_months', 12);
        $this->migrator->add('card.default_sessions_count', 10);
        $this->migrator->add('card.default_price_per_card', 250.00);
        $this->migrator->add('card.expiration_warning_days', [30, 7]);
    }
};
