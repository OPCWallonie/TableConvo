<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NotifyAdminOfLevelInterviewNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $applicant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Entretien de niveau requis — {$this->applicant->full_name}")
            ->greeting("Bonjour,")
            ->line("Un utilisateur souhaite s'inscrire à une table de conversation mais n'a pas encore de niveau attribué.")
            ->line("**Nom :** {$this->applicant->full_name}")
            ->line("**E-mail :** {$this->applicant->email}")
            ->line("**Entreprise :** " . ($this->applicant->company?->name ?? '—'))
            ->action('Voir le profil dans le back-office', url("/admin/users/{$this->applicant->id}/edit"))
            ->line('Contactez cet utilisateur pour planifier un entretien téléphonique et lui attribuer un niveau.');
    }
}
