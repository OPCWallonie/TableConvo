<?php

namespace App\Jobs;

use App\Mail\InvoicePaidMail;
use App\Models\Invoice;
use App\Services\Pdf\InvoicePdfService;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvoiceByEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Invoice $invoice) {}

    public function handle(InvoicePdfService $pdfService, EmailSettings $emailSettings, CompanySettings $companySettings): void
    {
        $pdfContent = $pdfService->getOrGenerate($this->invoice);
        $filename = "facture-{$this->invoice->invoice_number}.pdf";

        $mailable = new InvoicePaidMail($this->invoice, $pdfContent, $filename);

        $to = $this->invoice->order->user->email;
        Mail::to($to)->send($mailable);

        if ($emailSettings->admin_notifications_email) {
            Mail::bcc($emailSettings->admin_notifications_email)->send($mailable);
        }
    }
}
