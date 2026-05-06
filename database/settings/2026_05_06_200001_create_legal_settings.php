<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('legal.cgv_pdf_path', '');
        $this->migrator->add('legal.privacy_pdf_path', '');
    }
};
