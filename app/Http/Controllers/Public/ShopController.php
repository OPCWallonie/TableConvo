<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CardType;

class ShopController extends Controller
{
    public function show(CardType $cardType)
    {
        abort_unless($cardType->is_active, 404);

        return view('public.achat.show', compact('cardType'));
    }
}
