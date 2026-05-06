<?php

namespace App\Actions\Invoice;

use App\Models\Invoice;
use App\Models\Order;
use App\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateInvoiceAction
{
    public function __construct(
        private readonly GenerateInvoiceNumberAction $generateNumber,
        private readonly CompanySettings $companySettings
    ) {}

    public function execute(Order $order): Invoice
    {
        if ($order->invoice()->exists()) {
            throw new RuntimeException('invoice_already_exists');
        }

        return DB::transaction(function () use ($order) {
            $invoiceNumber = $this->generateNumber->execute();

            $billingSnapshot = [
                'recipient' => $order->company_snapshot,
                'issuer' => [
                    'company_name' => $this->companySettings->company_name,
                    'vat_number' => $this->companySettings->vat_number,
                    'rpm' => $this->companySettings->rpm,
                    'legal_form' => $this->companySettings->legal_form,
                    'street' => $this->companySettings->street,
                    'postal_code' => $this->companySettings->postal_code,
                    'city' => $this->companySettings->city,
                    'country' => $this->companySettings->country,
                    'iban' => $this->companySettings->iban,
                    'bic' => $this->companySettings->bic,
                    'bank_name' => $this->companySettings->bank_name,
                ],
            ];

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'issued_at' => now(),
                'total_ht' => $order->total_ht,
                'total_vat' => $order->total_vat,
                'total_ttc' => $order->total_ttc,
                'billing_snapshot' => $billingSnapshot,
            ]);

            activity()
                ->performedOn($invoice)
                ->log("Facture {$invoiceNumber} générée");

            return $invoice;
        });
    }
}
