<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Order\ProcessPaidOrderAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Mollie\MollieService;
use Illuminate\Http\Request;

class StubPaymentController extends Controller
{
    public function show(Order $order)
    {
        abort_unless(app(MollieService::class)->isStubMode(), 404);
        abort_unless(auth()->id() === $order->user_id, 403);

        return view('payment.stub', compact('order'));
    }

    public function confirm(Order $order, ProcessPaidOrderAction $action)
    {
        abort_unless(app(MollieService::class)->isStubMode(), 404);
        abort_unless(auth()->id() === $order->user_id, 403);
        abort_unless($order->status === OrderStatus::Pending, 422);

        $action->execute($order);

        return redirect()->route('espace.cartes')
            ->with('success', 'Paiement simulé avec succès. Vos cartes sont disponibles.');
    }

    public function fail(Order $order)
    {
        abort_unless(app(MollieService::class)->isStubMode(), 404);
        abort_unless(auth()->id() === $order->user_id, 403);
        abort_unless($order->status === OrderStatus::Pending, 422);

        $order->update(['status' => OrderStatus::Failed]);

        return redirect()->route('panier')
            ->with('error', 'Paiement simulé échoué.');
    }
}
