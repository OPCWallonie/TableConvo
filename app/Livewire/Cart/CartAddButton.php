<?php

namespace App\Livewire\Cart;

use Livewire\Component;

class CartAddButton extends Component
{
    public int $cardTypeId;
    public bool $added = false;

    public function addToCart(): void
    {
        $cart = session('cart.items', []);
        $key = (string) $this->cardTypeId;
        $cart[$key] = ($cart[$key] ?? 0) + 1;
        session(['cart.items' => $cart]);

        $this->added = true;
    }

    public function render()
    {
        return view('livewire.cart.cart-add-button');
    }
}
