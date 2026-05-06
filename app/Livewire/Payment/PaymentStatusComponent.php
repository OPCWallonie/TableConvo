<?php

namespace App\Livewire\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Poll;
use Livewire\Component;

class PaymentStatusComponent extends Component
{
    public int $orderId;
    public string $status = 'pending';
    public int $pollCount = 0;

    #[Poll(2000)]
    public function checkStatus(): void
    {
        if ($this->pollCount >= 15) {
            $this->status = 'timeout';
            return;
        }

        $order = Order::find($this->orderId);

        if (! $order) {
            $this->status = 'error';
            return;
        }

        $this->pollCount++;

        match ($order->status) {
            OrderStatus::Paid => $this->status = 'paid',
            OrderStatus::Failed => $this->status = 'failed',
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.payment.payment-status-component');
    }
}
