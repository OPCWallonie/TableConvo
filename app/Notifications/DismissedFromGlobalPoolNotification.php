<?php

namespace App\Notifications;

use App\Models\GlobalWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DismissedFromGlobalPoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GlobalWaitlistEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Vivier d'attente — Retrait de votre dossier")
            ->markdown('emails.global-pool.dismissed-from-pool', [
                'firstName' => $notifiable->first_name,
                'level'     => $this->entry->level->code,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'entry_id' => $this->entry->id,
            'level_id' => $this->entry->level_id,
        ];
    }
}
