<?php

namespace App\Http\Controllers\Member;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load(['company', 'level', 'registrations.conversationTable', 'cards']);

        $upcomingRegistrations = $user->registrations()
            ->with('conversationTable')
            ->whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()))
            ->whereIn('status', [RegistrationStatus::Registered->value])
            ->orderBy('registered_at')
            ->take(5)
            ->get();

        $activeCards = $user->activeCards()->with('cardType')->get();

        return view('espace.dashboard', compact('user', 'upcomingRegistrations', 'activeCards'));
    }
}
