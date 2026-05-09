<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Registration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $table  = $this->registration->conversationTable;
        $date   = $table->scheduled_at->translatedFormat('l d F Y à H:i');

        return (new MailMessage)
            ->subject("Rappel — Votre session « {$table->topic} » a lieu demain")
            ->greeting('Bonjour,')
            ->line("Nous vous rappelons que votre session « {$table->topic} » aura lieu le **{$date}**.")
            ->line("Niveau : {$table->level->code}")
            ->line("Lieu : {$table->location}")
            ->action('Mes inscriptions', url('/espace/inscriptions'))
            ->line('Si vous ne pouvez pas y assister, pensez à annuler votre inscription dans les délais prévus.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'table_id'        => $this->registration->conversation_table_id,
        ];
    }
}
