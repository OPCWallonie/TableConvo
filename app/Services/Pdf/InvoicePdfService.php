<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Settings\CompanySettings;
use App\Settings\InvoicingSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function __construct(
        private readonly CompanySettings $companySettings,
        private readonly InvoicingSettings $invoicingSettings,
    ) {}

    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing('order.items.cardType');

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'companySettings' => $this->companySettings,
            'invoicingSettings' => $this->invoicingSettings,
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    public function renderHtml(Invoice $invoice): string
    {
        $invoice->loadMissing('order.items.cardType');

        return view('pdfs.invoice', [
            'invoice' => $invoice,
            'companySettings' => $this->companySettings,
            'invoicingSettings' => $this->invoicingSettings,
        ])->render();
    }

    public function getOrGenerate(Invoice $invoice): string
    {
        $path = $this->storagePath($invoice);

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->get($path);
        }

        $content = $this->generate($invoice);
        Storage::disk('local')->put($path, $content);

        return $content;
    }

    public function storeToDisk(Invoice $invoice): string
    {
        $path = $this->storagePath($invoice);
        $content = $this->generate($invoice);
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    public function storagePath(Invoice $invoice): string
    {
        $year = $invoice->issued_at->format('Y');
        return "private/invoices/{$year}/{$invoice->invoice_number}.pdf";
    }
}
