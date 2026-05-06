<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;

class CartesController extends Controller
{
    public function index()
    {
        return view('espace.cartes.index');
    }
}
