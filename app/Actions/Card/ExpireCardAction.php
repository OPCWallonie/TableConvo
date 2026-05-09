<?php

namespace App\Actions\Card;

use App\Enums\CardStatus;
use App\Models\Card;

class ExpireCardAction
{
    public function execute(): int
    {
        $cards = Card::where('status', CardStatus::Active->value)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($cards as $card) {
            $card->update(['status' => CardStatus::Expired]);

            activity()
                ->performedOn($card)
                ->log('Carte expirée automatiquement');
        }

        return $cards->count();
    }
}
