<?php

namespace App\Livewire\Admin;

use App\Actions\Session\MarkAttendanceAction;
use App\Enums\RegistrationStatus;
use App\Models\ConversationTable;
use Filament\Notifications\Notification;
use Livewire\Component;

class AttendanceManager extends Component
{
    public ConversationTable $table;

    public array $presences = [];

    public bool $saved = false;

    public function mount(ConversationTable $table): void
    {
        $this->table = $table;

        $registrations = $table->registrations()
            ->where('status', RegistrationStatus::Registered->value)
            ->get();

        foreach ($registrations as $reg) {
            $this->presences[(string) $reg->user_id] = true;
        }
    }

    public function save(): void
    {
        $attendedUserIds = array_map(
            'intval',
            array_keys(array_filter($this->presences))
        );

        app(MarkAttendanceAction::class)->execute($this->table, $attendedUserIds, auth()->user());

        Notification::make()
            ->title('Présences enregistrées avec succès.')
            ->success()
            ->send();

        $this->saved = true;
        $this->dispatch('attendance-saved');
    }

    public function render(): \Illuminate\View\View
    {
        $registrations = $this->table->registrations()
            ->where('status', RegistrationStatus::Registered->value)
            ->with('user')
            ->get();

        return view('livewire.admin.attendance-manager', [
            'registrations' => $registrations,
        ]);
    }
}
