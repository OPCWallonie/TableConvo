<?php

use App\Models\CardType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Pdf\InvoicePdfService;
use App\Settings\CompanySettings;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $invoicing = app(InvoicingSettings::class);
    $invoicing->default_vat_rate = 21.00;
    $invoicing->payment_terms_days = 0;
    $invoicing->vat_exempt = false;
    $invoicing->save();

    $company = app(CompanySettings::class);
    $company->company_name = 'TableConvo SRL';
    $company->vat_number = 'BE0123456789';
    $company->iban = 'BE68539007547034';
    $company->bic = 'KREDBEBB';
    $company->rpm = 'RPM Liège';
    $company->save();
});

function makeInvoiceWithItems(): Invoice
{
    $buyerCompany = Company::factory()->create([
        'name' => 'Client SA',
        'vat_number' => 'BE0987654321',
    ]);
    $user = User::factory()->create(['company_id' => $buyerCompany->id]);
    $cardType = CardType::factory()->create(['name' => 'Carte 10 sessions', 'sessions_count' => 10, 'price' => 250.00]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'company_snapshot' => [
            'name' => 'Client SA',
            'vat_number' => 'BE0987654321',
            'street' => 'Rue Client 1',
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'country' => 'Belgique',
        ],
        'total_ht' => 206.61,
        'total_vat' => 43.39,
        'total_ttc' => 250.00,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'card_type_id' => $cardType->id,
        'quantity' => 1,
        'unit_price_ht' => 206.61,
        'vat_rate' => 21.00,
        'vat_amount' => 43.39,
        'total_ht' => 206.61,
        'total_ttc' => 250.00,
    ]);

    return Invoice::factory()->create([
        'order_id' => $order->id,
        'invoice_number' => 'FAC-2026-00001',
        'issued_at' => now(),
        'total_ht' => 206.61,
        'total_vat' => 43.39,
        'total_ttc' => 250.00,
        'billing_snapshot' => [
            'recipient' => [
                'name' => 'Client SA',
                'vat_number' => 'BE0987654321',
                'street' => 'Rue Client 1',
                'postal_code' => '1000',
                'city' => 'Bruxelles',
                'country' => 'Belgique',
            ],
            'issuer' => [
                'company_name' => 'TableConvo SRL',
                'vat_number' => 'BE0123456789',
                'iban' => 'BE68539007547034',
                'bic' => 'KREDBEBB',
                'rpm' => 'RPM Liège',
                'legal_form' => 'SRL',
                'street' => 'Rue de la Paix 1',
                'postal_code' => '4000',
                'city' => 'Liège',
                'country' => 'Belgique',
            ],
        ],
    ]);
}

it('generates a non-empty PDF', function () {
    $invoice = makeInvoiceWithItems();
    $pdf = app(InvoicePdfService::class)->generate($invoice);

    expect(strlen($pdf))->toBeGreaterThan(1000);
});

it('rendered HTML contains the invoice number', function () {
    $invoice = makeInvoiceWithItems();
    $html = app(InvoicePdfService::class)->renderHtml($invoice);

    expect($html)->toContain('FAC-2026-00001');
});

it('rendered HTML contains the issuer VAT number', function () {
    $invoice = makeInvoiceWithItems();
    $html = app(InvoicePdfService::class)->renderHtml($invoice);

    expect($html)->toContain('BE0123456789');
});

it('rendered HTML contains the recipient VAT number', function () {
    $invoice = makeInvoiceWithItems();
    $html = app(InvoicePdfService::class)->renderHtml($invoice);

    expect($html)->toContain('BE0987654321');
});

it('rendered HTML contains IBAN', function () {
    $invoice = makeInvoiceWithItems();
    $html = app(InvoicePdfService::class)->renderHtml($invoice);

    expect($html)->toContain('BE68539007547034');
});
