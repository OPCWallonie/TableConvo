<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CompanySettings extends Settings
{
    public string $company_name = '';
    public string $vat_number = '';
    public string $rpm = '';
    public string $legal_form = '';
    public string $street = '';
    public string $postal_code = '';
    public string $city = '';
    public string $country = 'Belgique';
    public string $iban = '';
    public string $bic = '';
    public string $bank_name = '';
    public string $email_contact = '';
    public string $phone = '';
    public string $website = '';
    public string $logo_path = '';

    public static function group(): string
    {
        return 'company';
    }
}
