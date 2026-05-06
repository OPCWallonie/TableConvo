<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.company_name', '');
        $this->migrator->add('company.vat_number', '');
        $this->migrator->add('company.rpm', '');
        $this->migrator->add('company.legal_form', '');
        $this->migrator->add('company.street', '');
        $this->migrator->add('company.postal_code', '');
        $this->migrator->add('company.city', '');
        $this->migrator->add('company.country', 'Belgique');
        $this->migrator->add('company.iban', '');
        $this->migrator->add('company.bic', '');
        $this->migrator->add('company.bank_name', '');
        $this->migrator->add('company.email_contact', '');
        $this->migrator->add('company.phone', '');
        $this->migrator->add('company.website', '');
        $this->migrator->add('company.logo_path', '');
    }
};
