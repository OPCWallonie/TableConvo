<?php

namespace App\Notifications\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAutoJoinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $newMember,
        private readonly Company $company,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nouveau membre dans {$this->company->name}")
            ->greeting("Bonjour,")
            ->line("{$this->newMember->full_name} ({$this->newMember->email}) a rejoint automatiquement **{$this->company->name}** grâce à son adresse e-mail professionnelle.")
            ->line('Aucune action requise de votre part.')
            ->action('Voir les membres', url('/espace/societe/membres'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'company_auto_joined',
            'new_member_id'  => $this->newMember->id,
            'new_member_name' => $this->newMember->full_name,
            'company_id'     => $this->company->id,
            'company_name'   => $this->company->name,
        ];
    }
}
