<?php

namespace App\Notifications\Company;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAdminVacantNotification extends Notification implements ShouldQueue
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
            ->subject("[Action requise] Aucun administrateur pour {$this->company->name}")
            ->greeting("Bonjour,")
            ->line("La société **{$this->company->name}** (ID : {$this->company->id}) n'a plus aucun membre éligible à la succession du rôle d'administrateur.")
            ->line('Intervention manuelle requise : réassignez un administrateur depuis le panel Filament.')
            ->action('Panel admin', url('/admin'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'company_admin_vacant',
            'company_id'   => $this->company->id,
            'company_name' => $this->company->name,
        ];
    }
}
