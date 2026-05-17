<?php

namespace App\Notifications;

use App\Enums\GlobalWaitlistSource;
use App\Models\GlobalWaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MovedToGlobalPoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly GlobalWaitlistEntry $entry,
        private readonly bool $wasRecredited = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $wasCancelled  = $this->entry->source === GlobalWaitlistSource::AdminCancelledRegistration;
        $sourceTable   = $this->entry->sourceRegistration?->conversationTable;

        return (new MailMessage)
            ->subject("Vivier d'attente — Niveau {$this->entry->level->code}")
            ->markdown('emails.global-pool.moved-to-pool', [
                'firstName'     => $notifiable->first_name,
                'level'         => $this->entry->level->code,
                'topic'         => $sourceTable?->topic,
                'date'          => $sourceTable?->scheduled_at?->translatedFormat('d F Y à H:i'),
                'wasCancelled'  => $wasCancelled,
                'wasRecredited' => $this->wasRecredited,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'entry_id' => $this->entry->id,
            'level_id' => $this->entry->level_id,
            'source'   => $this->entry->source->value,
        ];
    }
}
