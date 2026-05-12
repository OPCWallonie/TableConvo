<?php

namespace App\Notifications;

use App\Models\ConversationTable;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationRedirectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ConversationTable $oldTable,
        private readonly Registration $newRegistration
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $newTable = $this->newRegistration->conversationTable;
        $position = $this->newRegistration->waitlist_position;

        return (new MailMessage)
            ->subject('Vous avez été réorienté(e) vers une nouvelle session')
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Nous vous informons que votre inscription en liste d'attente pour la session « {$this->oldTable->topic} » du {$this->oldTable->scheduled_at->translatedFormat('d F Y')} a été réorientée vers une autre session compatible avec votre niveau.")
            ->line("**Nouvelle session :** « {$newTable->topic} »")
            ->line("**Date :** {$newTable->scheduled_at->translatedFormat('d F Y · H:i')}")
            ->line("**Niveau :** {$newTable->level->code}")
            ->line("Vous êtes actuellement en position **{$position}** de la liste d'attente. Vous serez notifié(e) si une place se libère.")
            ->action('Voir mes inscriptions', url(route('espace.inscriptions')))
            ->salutation("Cordialement,\nL'équipe TableConvo");
    }

    public function toArray(object $notifiable): array
    {
        $newTable = $this->newRegistration->conversationTable;

        return [
            'type'               => 'registration_redirected',
            'registration_id'    => $this->newRegistration->id,
            'old_table_id'       => $this->oldTable->id,
            'old_table_topic'    => $this->oldTable->topic,
            'new_table_id'       => $newTable->id,
            'new_table_topic'    => $newTable->topic,
            'new_scheduled_at'   => $newTable->scheduled_at->toIso8601String(),
            'waitlist_position'  => $this->newRegistration->waitlist_position,
        ];
    }
}
