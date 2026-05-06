<?php

namespace App\Http\Controllers;

use App\Actions\Order\ProcessPaidOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Mollie\MollieService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function mollie(Request $request, MollieService $mollieService, ProcessPaidOrderAction $processAction): Response
    {
        $paymentId = $request->input('id');

        if (! $paymentId) {
            return response('', 200);
        }

        $order = Order::where('mollie_payment_id', $paymentId)->first();

        if (! $order) {
            return response('', 404);
        }

        if ($order->status !== OrderStatus::Pending) {
            return response('OK', 200);
        }

        try {
            DB::transaction(function () use ($order, $paymentId, $mollieService, $processAction) {
                $order = Order::where('id', $order->id)->lockForUpdate()->first();

                if ($order->status !== OrderStatus::Pending) {
                    return;
                }

                $payment = $mollieService->fetchPayment($paymentId);

                match ($payment['status']) {
                    'paid' => $processAction->execute($order),
                    'failed', 'canceled', 'expired' => $order->update(['status' => OrderStatus::Failed]),
                    default => null,
                };
            });
        } catch (\Throwable $e) {
            Log::error('Mollie webhook error', ['error' => $e->getMessage(), 'payment_id' => $paymentId]);
        }

        return response('OK', 200);
    }
}
