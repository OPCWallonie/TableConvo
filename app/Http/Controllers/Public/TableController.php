<?php

namespace App\Http\Controllers\Public;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ConversationTable;
use Illuminate\View\View;

class TableController extends Controller
{
    public function show(ConversationTable $table): View
    {
        abort_if(
            $table->status === SessionStatus::Cancelled || $table->scheduled_at->isPast(),
            404
        );

        $table->loadCount([
            'registrations as registered_count' => fn ($q) => $q->where('status', RegistrationStatus::Registered->value),
            'registrations as waitlist_count'   => fn ($q) => $q->where('status', RegistrationStatus::Waitlist->value),
        ]);

        return view('public.tables.show', compact('table'));
    }
}
