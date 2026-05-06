<?php

namespace App\Actions\Order;

use App\Actions\Invoice\GenerateInvoiceAction;
use App\Enums\CardStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendInvoiceByEmailJob;
use App\Models\Card;
use App\Models\Order;
use App\Notifications\OrderPaidNotification;
use Illuminate\Support\Facades\DB;

class ProcessPaidOrderAction
{
    public function __construct(
        private readonly GenerateInvoiceAction $generateInvoice,
    ) {}

    public function execute(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Card::create([
                        'user_id' => $order->user_id,
                        'card_type_id' => $item->card_type_id,
                        'order_id' => $order->id,
                        'sessions_total' => $item->cardType->sessions_count,
                        'sessions_remaining' => $item->cardType->sessions_count,
                        'price_paid' => round(
                            $item->unit_price_ht * (1 + $item->vat_rate / 100),
                            2,
                            PHP_ROUND_HALF_UP
                        ),
                        'purchased_at' => now(),
                        'expires_at' => now()->addMonths($item->cardType->validity_months),
                        'status' => CardStatus::Active,
                    ]);
                }
            }

            $invoice = $this->generateInvoice->execute($order);

            SendInvoiceByEmailJob::dispatch($invoice);
        });

        $order->user->notify(new OrderPaidNotification($order));
    }
}
