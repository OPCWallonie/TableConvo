<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class InvoicingSettings extends Settings
{
    public string $invoice_number_prefix = 'FAC';
    public string $invoice_number_format = '{prefix}-{year}-{number:05d}';
    public bool $invoice_number_yearly_reset = true;
    public float $default_vat_rate = 21.00;
    public bool $vat_exempt = false;
    public string $vat_exempt_legal_mention = '';
    public int $payment_terms_days = 0;

    public static function group(): string
    {
        return 'invoicing';
    }
}
