<?php

namespace App\Notifications;

use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardExpirationWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Card $card,
        public readonly int $daysUntilExpiration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = $this->card->expires_at->translatedFormat('l d F Y');

        return (new MailMessage)
            ->subject('Votre carte expire bientôt')
            ->greeting('Bonjour,')
            ->line("Votre carte de {$this->card->sessions_total} séances expire dans {$this->daysUntilExpiration} jour(s) (le {$expiresAt}).")
            ->line("Il vous reste {$this->card->sessions_remaining} séance(s). Pensez à les utiliser avant l'expiration.")
            ->action("Voir l'agenda", url('/agenda'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'card_id'               => $this->card->id,
            'days_until_expiration' => $this->daysUntilExpiration,
            'sessions_remaining'    => $this->card->sessions_remaining,
        ];
    }
}
