<?php

namespace App\Notifications\Company;

use App\Models\CompanyJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyJoinApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Votre adhésion à {$this->joinRequest->company->name} a été approuvée")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Votre demande pour rejoindre **{$this->joinRequest->company->name}** a été approuvée.")
            ->line('Vous pouvez dès maintenant accéder à votre espace membre et acheter des sessions.')
            ->action('Accéder à mon espace', url('/espace'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_join_approved',
            'company_id'   => $this->joinRequest->company_id,
            'company_name' => $this->joinRequest->company->name,
        ];
    }
}
