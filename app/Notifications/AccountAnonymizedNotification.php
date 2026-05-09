<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountAnonymizedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $firstName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte TableConvo a été supprimé')
            ->greeting("Bonjour {$this->firstName},")
            ->line("Votre compte TableConvo a bien été anonymisé et supprimé, conformément à votre demande.")
            ->line("Vos données personnelles (nom, prénom, e-mail, téléphone) ont été effacées de nos systèmes.")
            ->line("Vos factures sont conservées pendant 7 ans pour des raisons légales, mais ne contiennent que les informations de votre société.")
            ->line("Merci de votre confiance et à bientôt.");
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'account_anonymized'];
    }
}
