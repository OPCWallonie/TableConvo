<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;

class PaymentReturnController extends Controller
{
    public function show(Order $order)
    {
        abort_unless(auth()->id() === $order->user_id, 403);

        return view('payment.return', compact('order'));
    }
}
