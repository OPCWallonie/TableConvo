<?php

namespace App\Livewire\Cart;

use App\Models\CardType;
use App\Settings\InvoicingSettings;
use Livewire\Component;

class CartComponent extends Component
{
    public array $items = [];

    public function mount(): void
    {
        $this->items = session('cart.items', []);
    }

    public function addItem(int $cardTypeId, int $qty = 1): void
    {
        $cardTypeId = (string) $cardTypeId;

        if (isset($this->items[$cardTypeId])) {
            $this->items[$cardTypeId] += $qty;
        } else {
            $this->items[$cardTypeId] = $qty;
        }

        $this->persistCart();
    }

    public function removeItem(int $cardTypeId): void
    {
        unset($this->items[(string) $cardTypeId]);
        $this->persistCart();
    }

    public function updateQuantity(int $cardTypeId, int $qty): void
    {
        if ($qty <= 0) {
            $this->removeItem($cardTypeId);
            return;
        }

        $this->items[(string) $cardTypeId] = $qty;
        $this->persistCart();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->persistCart();
    }

    public function getTotalHtProperty(): float
    {
        $vatRate = app(InvoicingSettings::class)->default_vat_rate;
        $total = 0.0;

        foreach ($this->getCartLines() as $line) {
            $ht = round($line['price'] / (1 + $vatRate / 100), 2, PHP_ROUND_HALF_UP);
            $total += round($ht * $line['quantity'], 2, PHP_ROUND_HALF_UP);
        }

        return round($total, 2, PHP_ROUND_HALF_UP);
    }

    public function getTotalVatProperty(): float
    {
        return round($this->totalTtc - $this->totalHt, 2, PHP_ROUND_HALF_UP);
    }

    public function getTotalTtcProperty(): float
    {
        $total = 0.0;

        foreach ($this->getCartLines() as $line) {
            $total += round($line['price'] * $line['quantity'], 2, PHP_ROUND_HALF_UP);
        }

        return round($total, 2, PHP_ROUND_HALF_UP);
    }

    public function getCartLines(): array
    {
        if (empty($this->items)) {
            return [];
        }

        $ids = array_keys($this->items);
        $cardTypes = CardType::whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];

        foreach ($this->items as $id => $qty) {
            $cardType = $cardTypes->get($id);
            if (! $cardType) {
                continue;
            }

            $lines[] = [
                'card_type_id' => (int) $id,
                'name' => $cardType->name,
                'sessions_count' => $cardType->sessions_count,
                'validity_months' => $cardType->validity_months,
                'price' => (float) $cardType->price,
                'quantity' => $qty,
                'subtotal' => round((float) $cardType->price * $qty, 2, PHP_ROUND_HALF_UP),
            ];
        }

        return $lines;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    private function persistCart(): void
    {
        session(['cart.items' => $this->items]);
    }

    public function render()
    {
        return view('livewire.cart.cart-component', [
            'lines' => $this->getCartLines(),
        ]);
    }
}
