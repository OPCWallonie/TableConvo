<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Order\CreateOrderFromCartAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, CreateOrderFromCartAction $action)
    {
        $request->validate([
            'cgv_accepted' => ['required', 'accepted'],
        ], [
            'cgv_accepted.required' => 'Vous devez accepter les Conditions Générales de Vente.',
            'cgv_accepted.accepted' => 'Vous devez accepter les Conditions Générales de Vente.',
        ]);

        $cartItems = session('cart.items', []);

        if (empty($cartItems)) {
            return redirect()->route('panier')->withErrors(['cart' => 'Votre panier est vide.']);
        }

        $result = $action->execute(auth()->user(), $cartItems);

        return redirect()->away($result['checkout_url']);
    }
}
