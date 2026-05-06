<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Pdf\InvoicePdfService;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('order')
            ->latest('issued_at')
            ->paginate(15);

        return view('espace.factures.index', compact('invoices'));
    }

    public function download(Invoice $invoice, InvoicePdfService $pdfService): Response
    {
        abort_unless($invoice->order->user_id === auth()->id(), 403);

        $content = $pdfService->getOrGenerate($invoice);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"facture-{$invoice->invoice_number}.pdf\"",
        ]);
    }
}
