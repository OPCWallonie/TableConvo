<?php

namespace App\Livewire\Agenda;

use App\Actions\Registration\CheckRegistrationRulesAction;
use App\Actions\Registration\RegisterUserToTableAction;
use App\Actions\User\RequestLevelInterviewAction;
use App\Enums\RegistrationStatus;
use App\Models\ConversationTable;
use App\Models\Registration;
use Livewire\Component;
use RuntimeException;

class RegisterButton extends Component
{
    public ConversationTable $table;

    // guest | registered | waitlisted | can_register | can_waitlist | no_level | blocked
    public string $status = 'loading';

    public ?string  $errorCode       = null;
    public ?string  $flashMessage    = null;
    public ?int     $waitlistPosition = null;

    private const ERROR_MESSAGES = [
        'session_not_available' => "Cette session n'est plus disponible.",
        'no_level'              => "Votre niveau n'a pas encore été déterminé. Un administrateur va vous contacter pour planifier un entretien téléphonique.",
        'wrong_level'           => "Cette table n'est pas ouverte à votre niveau de langue.",
        'deadline_passed'       => "Le délai d'inscription est dépassé.",
        'table_full'            => "Cette session est complète.",
        'weekly_limit_reached'  => "Vous avez atteint le nombre maximum d'inscriptions pour cette semaine.",
        'future_limit_reached'  => "Vous avez trop d'inscriptions futures simultanées.",
        'already_registered'    => "Vous êtes déjà inscrit à cette session.",
        'no_active_card'        => "Vous n'avez pas de carte active avec des sessions disponibles.",
        'cannot_cancel'         => "Cette inscription ne peut pas être annulée.",
        'session_unavailable'   => "Cette session n'est plus disponible.",
    ];

    public function mount(): void
    {
        $this->computeStatus();
    }

    public function register(): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');
            return;
        }

        $user  = auth()->user();
        $check = app(CheckRegistrationRulesAction::class)->execute($user, $this->table);

        if (! $check['allowed']) {
            if ($check['reason'] === 'no_level') {
                app(RequestLevelInterviewAction::class)->execute($user);
            }
            $this->computeStatus();
            $this->errorCode    = $check['reason'];  // set AFTER computeStatus so it is not cleared
            $this->flashMessage = null;
            return;
        }

        try {
            app(RegisterUserToTableAction::class)->execute($user, $this->table);
            $this->flashMessage = "Votre inscription est confirmée !";
            $this->errorCode    = null;
            $this->computeStatus();
        } catch (RuntimeException $e) {
            $this->computeStatus();
            $this->errorCode    = $e->getMessage();
            $this->flashMessage = null;
        }
    }

    public function joinWaitlist(): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login');
            return;
        }

        try {
            $registration = app(RegisterUserToTableAction::class)->execute(auth()->user(), $this->table, true);
            $this->flashMessage = "Vous êtes en liste d'attente (position #" . $registration->waitlist_position . ").";
            $this->errorCode    = null;
            $this->computeStatus();
        } catch (RuntimeException $e) {
            $this->computeStatus();
            $this->errorCode    = $e->getMessage();
            $this->flashMessage = null;
        }
    }

    public function errorMessage(): ?string
    {
        return $this->errorCode
            ? (self::ERROR_MESSAGES[$this->errorCode] ?? "Une erreur inattendue est survenue.")
            : null;
    }

    private function computeStatus(): void
    {
        if (! auth()->check()) {
            $this->status = 'guest';
            return;
        }

        $user = auth()->user();

        $existing = Registration::where('user_id', $user->id)
            ->where('conversation_table_id', $this->table->id)
            ->whereIn('status', [RegistrationStatus::Registered->value, RegistrationStatus::Waitlist->value])
            ->first();

        if ($existing) {
            if ($existing->status === RegistrationStatus::Registered) {
                $this->status = 'registered';
            } else {
                $this->status          = 'waitlisted';
                $this->waitlistPosition = $existing->waitlist_position;
            }
            $this->errorCode = null;
            return;
        }

        $check = app(CheckRegistrationRulesAction::class)->execute($user, $this->table);

        if ($check['allowed']) {
            $this->status    = 'can_register';
            $this->errorCode = null;
        } elseif ($check['reason'] === 'table_full') {
            $this->status    = 'can_waitlist';
            $this->errorCode = null;
        } elseif ($check['reason'] === 'no_level') {
            $this->status    = 'no_level';
            // errorCode left as-is: set by register() after computeStatus() call
        } else {
            $this->status    = 'blocked';
            $this->errorCode = $check['reason'];
        }
    }

    public function render()
    {
        return view('livewire.agenda.register-button', [
            'errorMessage' => $this->errorMessage(),
        ]);
    }
}
