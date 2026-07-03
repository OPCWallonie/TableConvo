<?php

namespace App\Notifications\Company;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAdminAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Company $company,
        private readonly bool $isSuperAdminForced = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $intro = $this->isSuperAdminForced
            ? "Un administrateur TableConvo vous a désigné(e) comme administrateur(trice) de la société **{$this->company->name}**."
            : "Vous êtes maintenant administrateur(trice) de la société **{$this->company->name}**.";

        return (new MailMessage)
            ->subject("Vous êtes administrateur de {$this->company->name}")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line($intro)
            ->line("Vous pouvez désormais approuver ou rejeter les demandes d'adhésion de nouveaux membres.")
            ->action('Gérer les membres', url('/espace/societe/membres'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'company_admin_assigned',
            'company_id'        => $this->company->id,
            'company_name'      => $this->company->name,
            'is_forced'         => $this->isSuperAdminForced,
        ];
    }
}
