<?php

namespace App\Livewire\Admin;

use App\Actions\Registration\CancelRegistrationAction;
use App\Actions\Registration\MoveRegistrationAction;
use App\Actions\Registration\PromoteFromWaitlistAction;
use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Models\Registration;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use RuntimeException;

class RegistrationsManager extends Component
{
    public ConversationTable $table;

    public ?int $moveRegistrationId = null;
    public ?int $targetTableId      = null;

    public function promote(int $registrationId): void
    {
        $registration = Registration::findOrFail($registrationId);
        try {
            app(PromoteFromWaitlistAction::class)->execute($registration, auth()->user());
            $this->table->refresh();
            Notification::make()->success()->title('Promotion effectuée.')->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($this->translateError($e->getMessage()))->send();
        }
    }

    public function cancel(int $registrationId): void
    {
        $registration = Registration::findOrFail($registrationId);
        try {
            app(CancelRegistrationAction::class)->execute($registration, auth()->user());
            $this->table->refresh();
            Notification::make()->success()->title('Inscription annulée.')->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($this->translateError($e->getMessage()))->send();
        }
    }

    public function openMoveModal(int $registrationId): void
    {
        $this->moveRegistrationId = $registrationId;
        $this->targetTableId      = null;
    }

    public function closeMoveModal(): void
    {
        $this->moveRegistrationId = null;
        $this->targetTableId      = null;
    }

    public function confirmMove(): void
    {
        if (! $this->moveRegistrationId || ! $this->targetTableId) {
            return;
        }

        $registration = Registration::findOrFail($this->moveRegistrationId);
        $newTable     = ConversationTable::findOrFail($this->targetTableId);

        try {
            app(MoveRegistrationAction::class)->execute($registration, $newTable, auth()->user());
            $this->closeMoveModal();
            $this->table->refresh();
            Notification::make()->success()->title('Inscription déplacée.')->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($this->translateError($e->getMessage()))->send();
        }
    }

    public function render(): View
    {
        $registrations = $this->table->registrations()
            ->with(['user', 'card.cardType'])
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->get();

        $registered = $registrations
            ->filter(fn ($r) => $r->status === RegistrationStatus::Registered)
            ->sortBy('registered_at')
            ->values();

        $waitlist = $registrations
            ->filter(fn ($r) => $r->status === RegistrationStatus::Waitlist)
            ->sortBy('waitlist_position')
            ->values();

        $isFull = $registered->count() >= $this->table->max_participants;

        $availableTables = ConversationTable::where('status', SessionStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->where('id', '!=', $this->table->id)
            ->orderBy('scheduled_at')
            ->get()
            ->mapWithKeys(fn (ConversationTable $t) => [
                $t->id => $t->topic . ' — ' . $t->scheduled_at->format('d/m/Y H:i'),
            ]);

        $movingRegistration = $this->moveRegistrationId
            ? $registrations->find($this->moveRegistrationId)
            : null;

        return view('livewire.admin.registrations-manager', compact(
            'registered',
            'waitlist',
            'isFull',
            'availableTables',
            'movingRegistration',
        ));
    }

    private function translateError(string $code): string
    {
        return match ($code) {
            'registration_not_on_waitlist'  => "Cette inscription n'est pas en liste d'attente.",
            'table_still_full'              => "La table est toujours complète.",
            'no_active_card_for_promotion'  => "L'utilisateur n'a pas de carte active.",
            'target_table_not_scheduled'    => "La table cible n'est pas ouverte aux inscriptions.",
            'target_table_in_past'          => "La table cible est dans le passé.",
            'target_table_full'             => "La table cible est complète.",
            'user_already_on_target_table'  => "L'utilisateur est déjà inscrit sur cette table.",
            'cannot_cancel'                 => "Cette inscription ne peut pas être annulée.",
            'deadline_passed'               => "Le délai d'annulation est dépassé.",
            'session_unavailable'           => "La session n'est plus disponible.",
            default                         => "Une erreur est survenue.",
        };
    }
}
