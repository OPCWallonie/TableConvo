<?php

namespace App\Http\Controllers\Public;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ConversationTable;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $levelId = $request->query('level_id') ?: null;

        $tables = ConversationTable::with('level')
            ->where('status', SessionStatus::Scheduled->value)
            ->where('scheduled_at', '>=', now())
            ->when($levelId, fn ($q) => $q->where('level_id', $levelId))
            ->withCount([
                'registrations as registered_count' => fn ($q) => $q->where('status', RegistrationStatus::Registered->value),
                'registrations as waitlist_count'   => fn ($q) => $q->where('status', RegistrationStatus::Waitlist->value),
            ])
            ->orderBy('scheduled_at')
            ->get();

        $levels = Level::where('is_active', true)->orderBy('sort_order')->get();

        return view('public.agenda.index', compact('tables', 'levels', 'levelId'));
    }
}
