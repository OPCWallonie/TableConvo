<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        private readonly string $pdfContent,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        $emailSettings = app(EmailSettings::class);
        $companySettings = app(CompanySettings::class);

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                $emailSettings->from_email ?: config('mail.from.address'),
                $emailSettings->from_name ?: $companySettings->company_name ?: config('mail.from.name'),
            ),
            replyTo: $emailSettings->reply_to ? [
                new \Illuminate\Mail\Mailables\Address($emailSettings->reply_to),
            ] : [],
            subject: "Votre facture {$this->invoice->invoice_number} — TableConvo",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-paid',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
