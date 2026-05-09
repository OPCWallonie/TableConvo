<?php

namespace App\Notifications;

use App\Models\ConversationTable;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ConversationTable $table,
        public readonly Registration $registration,
        public readonly string $compensationType,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->table->scheduled_at->translatedFormat('l d F Y à H:i');
        $topic = $this->table->topic;

        $mail = (new MailMessage)
            ->subject("Session du {$this->table->scheduled_at->format('d/m/Y')} annulée")
            ->greeting('Bonjour,')
            ->line("La session « {$topic} » prévue le {$date} a été annulée.")
            ->line("Raison communiquée : {$this->reason}");

        return match ($this->compensationType) {
            'recredit_and_extend' => $mail->line(
                "Votre séance a été recréditée sur votre carte et la validité de cette dernière a été prolongée."
            ),
            'recredit_only' => $mail->line(
                "Votre séance a été recréditée sur votre carte. La validité de votre carte n'a pas été modifiée (elle expire encore dans suffisamment longtemps)."
            ),
            'expired_no_compensation' => $mail->line(
                "Votre carte étant expirée, la séance n'a pas pu être recréditée."
            ),
            'waitlist_notice' => $mail->line(
                "Vous étiez en liste d'attente pour cette session. Aucune action de votre part n'est requise."
            ),
            default => $mail,
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'table_id'         => $this->table->id,
            'registration_id'  => $this->registration->id,
            'compensation_type' => $this->compensationType,
            'reason'           => $this->reason,
        ];
    }
}
