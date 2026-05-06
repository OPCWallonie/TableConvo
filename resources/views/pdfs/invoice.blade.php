<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }

        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .issuer { flex: 1; }
        .issuer h2 { font-size: 16px; font-weight: bold; color: #1d4ed8; margin-bottom: 4px; }
        .issuer p { font-size: 10px; color: #555; margin: 1px 0; }
        .invoice-meta { text-align: right; }
        .invoice-meta h1 { font-size: 22px; font-weight: bold; color: #1d4ed8; text-transform: uppercase; letter-spacing: 2px; }
        .invoice-meta p { font-size: 10px; color: #555; margin: 2px 0; }

        .divider { border-top: 2px solid #1d4ed8; margin: 16px 0; }
        .divider-light { border-top: 1px solid #e5e7eb; margin: 12px 0; }

        .addresses { display: flex; gap: 40px; margin-bottom: 24px; }
        .address-block { flex: 1; }
        .address-block h4 { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
        .address-block p { font-size: 10px; margin: 1px 0; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items thead th {
            background: #1d4ed8;
            color: white;
            padding: 7px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody tr:nth-child(even) { background: #f8fafc; }
        table.items tbody td { padding: 7px 10px; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
        table.items tbody td.right { text-align: right; }

        .totals { margin-left: auto; width: 220px; }
        .totals-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 10px; }
        .totals-row.total { font-weight: bold; font-size: 12px; color: #1d4ed8; border-top: 2px solid #1d4ed8; padding-top: 6px; margin-top: 4px; }

        .footer { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .footer p { font-size: 9px; color: #6b7280; margin: 2px 0; }
        .badge-paid { display: inline-block; background: #d1fae5; color: #065f46; font-weight: bold; font-size: 10px; padding: 3px 10px; border-radius: 12px; margin-top: 8px; }

        .bank-info { margin-top: 12px; padding: 8px; background: #f0f9ff; border-left: 3px solid #1d4ed8; font-size: 10px; }
    </style>
</head>
<body>
<div class="page">

    {{-- En-tête --}}
    <div class="header">
        <div class="issuer">
            @php $issuer = $invoice->billing_snapshot['issuer'] ?? []; @endphp
            @if(!empty($companySettings->logo_path))
                <img src="{{ Storage::disk('public')->path($companySettings->logo_path) }}"
                     style="max-height:50px; max-width:160px; margin-bottom:8px;" alt="Logo">
            @endif
            <h2>{{ $issuer['company_name'] ?? '' }}</h2>
            @if(!empty($issuer['legal_form'])) <p>{{ $issuer['legal_form'] }}</p> @endif
            @if(!empty($issuer['street'])) <p>{{ $issuer['street'] }}</p> @endif
            @if(!empty($issuer['postal_code']) || !empty($issuer['city']))
                <p>{{ trim(($issuer['postal_code'] ?? '') . ' ' . ($issuer['city'] ?? '')) }}</p>
            @endif
            @if(!empty($issuer['country'])) <p>{{ $issuer['country'] }}</p> @endif
            @if(!empty($issuer['vat_number'])) <p>TVA : {{ $issuer['vat_number'] }}</p> @endif
            @if(!empty($issuer['rpm'])) <p>RPM : {{ $issuer['rpm'] }}</p> @endif
            @if(!empty($companySettings->email_contact)) <p>{{ $companySettings->email_contact }}</p> @endif
        </div>

        <div class="invoice-meta">
            <h1>Facture</h1>
            <p><strong>N° :</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Date d'émission :</strong> {{ $invoice->issued_at->format('d/m/Y') }}</p>
            @php $termsDays = $invoicingSettings->payment_terms_days ?? 0; @endphp
            <p><strong>Échéance :</strong>
                {{ $termsDays > 0
                    ? $invoice->issued_at->addDays($termsDays)->format('d/m/Y')
                    : 'Paiement immédiat' }}
            </p>
        </div>
    </div>

    <div class="divider"></div>

    {{-- Adresses --}}
    <div class="addresses">
        <div class="address-block">
            <h4>Émetteur</h4>
            @if(!empty($issuer['iban']))
                <p>IBAN : {{ $issuer['iban'] }}</p>
            @endif
            @if(!empty($issuer['bic']))
                <p>BIC : {{ $issuer['bic'] }}</p>
            @endif
        </div>
        <div class="address-block">
            @php $recipient = $invoice->billing_snapshot['recipient'] ?? []; @endphp
            <h4>Facturé à</h4>
            <p><strong>{{ $recipient['name'] ?? '' }}</strong></p>
            @if(!empty($recipient['street'])) <p>{{ $recipient['street'] }}</p> @endif
            @if(!empty($recipient['postal_code']) || !empty($recipient['city']))
                <p>{{ trim(($recipient['postal_code'] ?? '') . ' ' . ($recipient['city'] ?? '')) }}</p>
            @endif
            @if(!empty($recipient['country'])) <p>{{ $recipient['country'] }}</p> @endif
            @if(!empty($recipient['vat_number'])) <p>TVA : {{ $recipient['vat_number'] }}</p> @endif
        </div>
    </div>

    {{-- Tableau des lignes --}}
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qté</th>
                <th class="right">PU HT</th>
                <th class="right">TVA %</th>
                <th class="right">Total HT</th>
                <th class="right">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->order->items as $item)
                <tr>
                    <td>{{ $item->cardType->name }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_price_ht, 2, ',', ' ') }} €</td>
                    <td class="right">{{ number_format($item->vat_rate, 0) }} %</td>
                    <td class="right">{{ number_format($item->total_ht, 2, ',', ' ') }} €</td>
                    <td class="right">{{ number_format($item->total_ttc, 2, ',', ' ') }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totaux --}}
    <div class="totals">
        <div class="totals-row">
            <span>Total HT</span>
            <span>{{ number_format($invoice->total_ht, 2, ',', ' ') }} €</span>
        </div>
        <div class="totals-row">
            <span>TVA</span>
            <span>{{ number_format($invoice->total_vat, 2, ',', ' ') }} €</span>
        </div>
        <div class="totals-row total">
            <span>Total TTC</span>
            <span>{{ number_format($invoice->total_ttc, 2, ',', ' ') }} €</span>
        </div>
    </div>

    {{-- Pied de page --}}
    <div class="footer">
        @if($invoice->order->paid_at)
            <span class="badge-paid">✓ Facture acquittée le {{ $invoice->order->paid_at->format('d/m/Y') }}</span>
        @endif

        @if($invoicingSettings->vat_exempt && !empty($invoicingSettings->vat_exempt_legal_mention))
            <p style="margin-top:8px;">{{ $invoicingSettings->vat_exempt_legal_mention }}</p>
        @endif

        <div class="divider-light" style="margin-top:12px;"></div>
        <p>Conditions générales de vente : {{ config('app.url') }}/cgv</p>
        @if(!empty($issuer['iban']))
            <p>Paiement par virement : IBAN {{ $issuer['iban'] }}{{ !empty($issuer['bic']) ? ' · BIC ' . $issuer['bic'] : '' }}</p>
        @endif
    </div>
</div>
</body>
</html>
