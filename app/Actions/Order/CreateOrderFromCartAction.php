<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CardType;
use App\Models\User;
use App\Services\Mollie\MollieService;
use App\Settings\InvoicingSettings;
use Illuminate\Support\Facades\DB;

class CreateOrderFromCartAction
{
    public function __construct(
        private readonly MollieService $mollie,
        private readonly InvoicingSettings $invoicing,
    ) {}

    public function execute(User $user, array $cartItems): array
    {
        abort_if(empty($cartItems), 422, 'Le panier est vide.');
        abort_unless($user->company, 422, 'Aucune société rattachée à ce compte.');

        $vatRate = $this->invoicing->default_vat_rate;

        $result = DB::transaction(function () use ($user, $cartItems, $vatRate) {
            $company = $user->company;

            $companySnapshot = [
                'name' => $company->name,
                'vat_number' => $company->vat_number,
                'street' => $company->street,
                'postal_code' => $company->postal_code,
                'city' => $company->city,
                'country' => $company->country,
                'billing_email' => $company->billing_email,
            ];

            $totalHt = 0.0;
            $totalVat = 0.0;
            $totalTtc = 0.0;

            $itemsData = [];

            foreach ($cartItems as $cardTypeId => $quantity) {
                $cardType = CardType::findOrFail($cardTypeId);
                $quantity = (int) $quantity;

                $unitPriceTtc = (float) $cardType->price;
                $unitPriceHt = round($unitPriceTtc / (1 + $vatRate / 100), 2, PHP_ROUND_HALF_UP);
                $unitVatAmount = round($unitPriceTtc - $unitPriceHt, 2, PHP_ROUND_HALF_UP);
                $lineHt = round($unitPriceHt * $quantity, 2, PHP_ROUND_HALF_UP);
                $lineTtc = round($unitPriceTtc * $quantity, 2, PHP_ROUND_HALF_UP);
                $lineVat = round($lineTtc - $lineHt, 2, PHP_ROUND_HALF_UP);

                $totalHt = round($totalHt + $lineHt, 2, PHP_ROUND_HALF_UP);
                $totalVat = round($totalVat + $lineVat, 2, PHP_ROUND_HALF_UP);
                $totalTtc = round($totalTtc + $lineTtc, 2, PHP_ROUND_HALF_UP);

                $itemsData[] = [
                    'card_type_id' => $cardType->id,
                    'quantity' => $quantity,
                    'unit_price_ht' => $unitPriceHt,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $lineVat,
                    'total_ht' => $lineHt,
                    'total_ttc' => $lineTtc,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'company_snapshot' => $companySnapshot,
                'total_ht' => $totalHt,
                'total_vat' => $totalVat,
                'total_ttc' => $totalTtc,
                'status' => OrderStatus::Pending,
            ]);

            foreach ($itemsData as $item) {
                OrderItem::create(array_merge($item, ['order_id' => $order->id]));
            }

            $payment = $this->mollie->createPayment($order);

            $order->update(['mollie_payment_id' => $payment['payment_id']]);

            session()->forget('cart.items');

            return ['order' => $order, 'checkout_url' => $payment['checkout_url']];
        });

        return $result;
    }
}
