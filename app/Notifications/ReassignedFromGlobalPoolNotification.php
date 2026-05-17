<?php

namespace App\Notifications;

use App\Enums\RegistrationStatus;
use App\Models\GlobalWaitlistEntry;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReassignedFromGlobalPoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly GlobalWaitlistEntry $entry,
        private readonly Registration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $table      = $this->registration->conversationTable;
        $isRegistered = $this->registration->status === RegistrationStatus::Registered;

        return (new MailMessage)
            ->subject("Une session vous a été attribuée — {$table->topic}")
            ->markdown('emails.global-pool.reassigned-from-pool', [
                'firstName'       => $notifiable->first_name,
                'level'           => $this->entry->level->code,
                'topic'           => $table->topic,
                'scheduledAt'     => $table->scheduled_at->translatedFormat('l d F Y à H:i'),
                'isRegistered'    => $isRegistered,
                'waitlistPosition'=> $this->registration->waitlist_position,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'entry_id'        => $this->entry->id,
            'registration_id' => $this->registration->id,
        ];
    }
}
