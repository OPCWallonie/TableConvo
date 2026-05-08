<?php

namespace App\Http\Controllers\Member;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class RegistrationsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $all = $user->registrations()
            ->with('conversationTable.level')
            ->get();

        $upcoming = $all
            ->filter(fn ($r) =>
                in_array($r->status, [RegistrationStatus::Registered, RegistrationStatus::Waitlist], true)
                && $r->conversationTable->scheduled_at->isFuture()
            )
            ->sortBy('conversationTable.scheduled_at')
            ->values();

        $past = $all
            ->diff($upcoming)
            ->sortByDesc('conversationTable.scheduled_at')
            ->values();

        return view('espace.inscriptions.index', compact('upcoming', 'past'));
    }
}
