<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CardType;

class PricingController extends Controller
{
    public function index()
    {
        $cardTypes = CardType::where('is_active', true)
            ->orderBy('price')
            ->get();

        return view('public.tarifs', compact('cardTypes'));
    }
}
