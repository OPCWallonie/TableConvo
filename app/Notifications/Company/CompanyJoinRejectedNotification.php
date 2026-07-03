<?php

namespace App\Notifications\Company;

use App\Models\CompanyJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyJoinRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanyJoinRequest $joinRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Votre demande d'adhésion à {$this->joinRequest->company->name} n'a pas abouti")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Votre demande pour rejoindre **{$this->joinRequest->company->name}** a été refusée.");

        if ($this->joinRequest->rejection_reason) {
            $mail->line("Motif : {$this->joinRequest->rejection_reason}");
        }

        return $mail->line("Si vous pensez qu'il s'agit d'une erreur, contactez votre responsable ou TableConvo.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'company_join_rejected',
            'company_id'       => $this->joinRequest->company_id,
            'company_name'     => $this->joinRequest->company->name,
            'rejection_reason' => $this->joinRequest->rejection_reason,
        ];
    }
}
