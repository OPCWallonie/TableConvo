<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Settings\LegalSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalController extends Controller
{
    public function cgv(LegalSettings $settings)
    {
        if ($settings->cgv_pdf_path && Storage::disk('public')->exists($settings->cgv_pdf_path)) {
            return Storage::disk('public')->response($settings->cgv_pdf_path, 'CGV-TableConvo.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return view('public.legal.cgv-placeholder');
    }

    public function confidentialite(LegalSettings $settings)
    {
        if ($settings->privacy_pdf_path && Storage::disk('public')->exists($settings->privacy_pdf_path)) {
            return Storage::disk('public')->response($settings->privacy_pdf_path, 'Confidentialite-TableConvo.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return view('public.legal.confidentialite-placeholder');
    }
}
