<?php

namespace App\Livewire\Espace;

use App\Actions\GlobalWaitlist\DismissGlobalWaitlistEntryAction;
use App\Models\GlobalWaitlistEntry;
use Livewire\Component;

class DismissPoolButton extends Component
{
    public int $entryId;
    public bool $showDialog = false;
    public string $reason = '';

    public function mount(int $entryId): void
    {
        $this->entryId = $entryId;
    }

    public function openDismissDialog(int $entryId): void
    {
        $this->entryId  = $entryId;
        $this->reason   = '';
        $this->showDialog = true;
    }

    public function closeDismissDialog(): void
    {
        $this->showDialog = false;
    }

    public function confirmDismiss(): void
    {
        $entry = GlobalWaitlistEntry::findOrFail($this->entryId);

        try {
            app(DismissGlobalWaitlistEntryAction::class)->execute(
                entry:  $entry,
                actor:  auth()->user(),
                reason: blank($this->reason) ? 'À ma demande' : $this->reason,
                byUser: true,
            );
        } catch (\RuntimeException) {
            $this->addError('general', 'Action non autorisée.');
            return;
        }

        $this->redirect(route('espace.inscriptions'), navigate: true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.espace.dismiss-pool-button');
    }
}
