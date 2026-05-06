<?php

namespace App\Actions\Card;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseCardAction
{
    public function execute(User $user, CardType $cardType, Order $order): Card
    {
        return DB::transaction(function () use ($user, $cardType, $order) {
            $card = Card::create([
                'user_id' => $user->id,
                'card_type_id' => $cardType->id,
                'order_id' => $order->id,
                'sessions_total' => $cardType->sessions_count,
                'sessions_remaining' => $cardType->sessions_count,
                'price_paid' => $cardType->price,
                'purchased_at' => now(),
                'expires_at' => Carbon::now()->addMonths($cardType->validity_months),
                'status' => CardStatus::Active,
            ]);

            activity()
                ->performedOn($card)
                ->causedBy($user)
                ->log('Carte achetée');

            return $card;
        });
    }
}
