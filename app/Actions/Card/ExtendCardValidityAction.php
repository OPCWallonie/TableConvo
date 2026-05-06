<?php

namespace App\Actions\Card;

use App\Models\Card;
use App\Models\User;
use Carbon\Carbon;

class ExtendCardValidityAction
{
    public function execute(Card $card, int $days, User $admin): Card
    {
        $card->update([
            'expires_at' => Carbon::parse($card->expires_at)->addDays($days),
        ]);

        activity()
            ->performedOn($card)
            ->causedBy($admin)
            ->withProperties(['extended_by_days' => $days])
            ->log("Validité prolongée de {$days} jours par l'admin");

        return $card->fresh();
    }
}
