<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LegalSettings extends Settings
{
    public string $cgv_pdf_path = '';
    public string $privacy_pdf_path = '';

    public static function group(): string
    {
        return 'legal';
    }
}
