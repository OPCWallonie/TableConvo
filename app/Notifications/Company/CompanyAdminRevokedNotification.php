<?php

namespace App\Notifications\Company;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAdminRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Company $company,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Vos droits d'administration sur {$this->company->name} ont été transférés")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Vos droits d'administration pour la société **{$this->company->name}** ont été transférés à un autre membre.")
            ->line("Vous restez membre de la société et conservez l'accès à votre espace.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_admin_revoked',
            'company_id'   => $this->company->id,
            'company_name' => $this->company->name,
        ];
    }
}
