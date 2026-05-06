<?php

use App\Actions\Invoice\GenerateInvoiceAction;
use App\Models\Order;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var CompanySettings $companySettings */
    $companySettings = app(CompanySettings::class);
    $companySettings->company_name = 'TableConvo SPRL';
    $companySettings->vat_number = 'BE0123456789';
    $companySettings->rpm = 'RPM Bruxelles';
    $companySettings->iban = 'BE68539007547034';
    $companySettings->save();
});

it('generates invoice with fully populated billing snapshot', function () {
    $order = Order::factory()->create([
        'company_snapshot' => [
            'name' => 'Client SA',
            'vat_number' => 'BE0987654321',
            'street' => 'Rue du Test 1',
            'postal_code' => '1000',
            'city' => 'Bruxelles',
        ],
        'total_ht' => 206.61,
        'total_vat' => 43.39,
        'total_ttc' => 250.00,
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($order);

    expect($invoice->billing_snapshot['recipient']['name'])->toBe('Client SA');
    expect($invoice->billing_snapshot['issuer']['company_name'])->toBe('TableConvo SPRL');
    expect($invoice->billing_snapshot['issuer']['vat_number'])->toBe('BE0123456789');
    expect($invoice->billing_snapshot['issuer']['iban'])->toBe('BE68539007547034');
    expect($invoice->total_ttc)->toEqual('250.00');
    expect($invoice->invoice_number)->toStartWith('FAC-');
});

it('throws on second call for the same order (idempotence)', function () {
    $order = Order::factory()->create();

    app(GenerateInvoiceAction::class)->execute($order);

    expect(fn () => app(GenerateInvoiceAction::class)->execute($order))
        ->toThrow(RuntimeException::class, 'invoice_already_exists');
});
