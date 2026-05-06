<?php

namespace App\Actions\Invoice;

use App\Models\InvoiceCounter;
use App\Settings\InvoicingSettings;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceNumberAction
{
    public function __construct(private readonly InvoicingSettings $settings) {}

    public function execute(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            $counter = InvoiceCounter::lockForUpdate()->firstOrCreate(
                ['year' => $year],
                ['last_number' => 0]
            );

            $counter->increment('last_number');
            $counter->refresh();

            return $this->formatNumber($counter->last_number, $year);
        });
    }

    private function formatNumber(int $number, int $year): string
    {
        $prefix = $this->settings->invoice_number_prefix;
        $formatted = str_pad($number, 5, '0', STR_PAD_LEFT);

        return str_replace(
            ['{prefix}', '{year}', '{number:05d}'],
            [$prefix, $year, $formatted],
            $this->settings->invoice_number_format
        );
    }
}
