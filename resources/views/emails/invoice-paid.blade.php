<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #1d4ed8; }
        .highlight { background: #f0f9ff; border-left: 4px solid #1d4ed8; padding: 12px 16px; margin: 16px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Votre commande a été confirmée</h2>

    <p>Bonjour {{ $invoice->order->user->first_name }},</p>

    <p>Nous avons bien reçu votre paiement. Votre facture est disponible en pièce jointe.</p>

    <div class="highlight">
        <strong>Facture n° {{ $invoice->invoice_number }}</strong><br>
        Date : {{ $invoice->issued_at->format('d/m/Y') }}<br>
        Montant TTC : {{ number_format($invoice->total_ttc, 2, ',', ' ') }} €
    </div>

    <p>Vos cartes de sessions sont maintenant disponibles dans votre espace membre.</p>

    <p>Rendez-vous sur <a href="{{ route('espace.cartes') }}">votre espace personnel</a> pour consulter vos cartes et vous inscrire aux tables de conversation.</p>

    <p>Cordialement,<br>
    L'équipe TableConvo</p>

    <div class="footer">
        <p>Cette facture est établie au nom de votre société conformément à nos CGV.</p>
        <p><a href="{{ route('cgv') }}">Conditions Générales de Vente</a></p>
    </div>
</div>
</body>
</html>
