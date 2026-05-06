<?php

use App\Actions\Invoice\GenerateInvoiceNumberAction;
use App\Models\InvoiceCounter;
use App\Settings\InvoicingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sequential invoice numbers', function () {
    $settings = app(InvoicingSettings::class);
    $action = new GenerateInvoiceNumberAction($settings);

    $first = $action->execute();
    $second = $action->execute();
    $third = $action->execute();

    $year = now()->year;
    expect($first)->toBe("FAC-{$year}-00001");
    expect($second)->toBe("FAC-{$year}-00002");
    expect($third)->toBe("FAC-{$year}-00003");
});

it('never generates duplicate invoice numbers under concurrency', function () {
    $settings = app(InvoicingSettings::class);
    $action = new GenerateInvoiceNumberAction($settings);

    $numbers = collect(range(1, 10))->map(fn () => $action->execute());

    expect($numbers->unique()->count())->toBe(10);
});
