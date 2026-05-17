<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPromotedFromWaitlistNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Registration $registration) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $table = $this->registration->conversationTable;

        return (new MailMessage)
            ->subject('Votre inscription est confirmée !')
            ->greeting('Bonne nouvelle !')
            ->line("Une place s'est libérée pour la session « {$table->topic} » du {$table->scheduled_at->translatedFormat('l d F Y à H:i')}.")
            ->line('Votre inscription est confirmée.')
            ->action('Voir mes inscriptions', url(route('espace.inscriptions')));
    }

    public function toArray(object $notifiable): array
    {
        $table = $this->registration->conversationTable;

        return [
            'registration_id' => $this->registration->id,
            'table_id'        => $table->id,
            'scheduled_at'    => $table->scheduled_at->toIso8601String(),
        ];
    }
}
