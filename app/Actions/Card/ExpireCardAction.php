<?php

namespace App\Actions\Card;

use App\Enums\CardStatus;
use App\Models\Card;

class ExpireCardAction
{
    public function execute(): int
    {
        return Card::where('status', CardStatus::Active)
            ->where('expires_at', '<=', now())
            ->update(['status' => CardStatus::Expired]);
    }
}
