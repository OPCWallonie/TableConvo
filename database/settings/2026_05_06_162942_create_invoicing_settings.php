<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('invoicing.invoice_number_prefix', 'FAC');
        $this->migrator->add('invoicing.invoice_number_format', '{prefix}-{year}-{number:05d}');
        $this->migrator->add('invoicing.invoice_number_yearly_reset', true);
        $this->migrator->add('invoicing.default_vat_rate', 21.00);
        $this->migrator->add('invoicing.vat_exempt', false);
        $this->migrator->add('invoicing.vat_exempt_legal_mention', '');
        $this->migrator->add('invoicing.payment_terms_days', 0);
    }
};
