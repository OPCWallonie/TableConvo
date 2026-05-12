<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if ($this->migrator->exists('theme.card_design')) {
            return;
        }
        $this->migrator->add('theme.card_design', 'wallet');
    }
};
