<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('theme.color_primary', '#2563eb');
        $this->migrator->add('theme.color_accent', '#d97706');
        $this->migrator->add('theme.color_surface', '#f3f4f6');
        $this->migrator->add('theme.card_design', 'stamp');
    }
};
