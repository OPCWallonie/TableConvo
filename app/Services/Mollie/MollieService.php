<?php

namespace App\Services\Mollie;

use App\Models\Order;
use App\Settings\MollieSettings;
use Illuminate\Support\Str;
use Mollie\Laravel\Facades\Mollie;

class MollieService
{
    public function __construct(private readonly MollieSettings $settings) {}

    public function isStubMode(): bool
    {
        return empty($this->settings->api_key);
    }

    public function createPayment(Order $order): array
    {
        if ($this->isStubMode()) {
            return [
                'payment_id' => 'stub_' . Str::random(20),
                'checkout_url' => route('paiement.stub', ['order' => $order]),
            ];
        }

        $payment = Mollie::api()->payments()->create([
            'amount' => [
                'currency' => 'EUR',
                'value' => number_format($order->total_ttc, 2, '.', ''),
            ],
            'description' => "Commande #{$order->id} — TableConvo",
            'redirectUrl' => route('paiement.retour', ['order' => $order]),
            'webhookUrl' => route('webhooks.mollie'),
            'metadata' => ['order_id' => $order->id],
        ]);

        return [
            'payment_id' => $payment->id,
            'checkout_url' => $payment->getCheckoutUrl(),
        ];
    }

    public function fetchPayment(string $paymentId): array
    {
        if ($this->isStubMode() || str_starts_with($paymentId, 'stub_')) {
            return ['status' => 'paid', 'paid_at' => now()];
        }

        $payment = Mollie::api()->payments()->get($paymentId);

        return [
            'status' => $payment->status,
            'paid_at' => $payment->status === 'paid' ? now() : null,
        ];
    }
}
