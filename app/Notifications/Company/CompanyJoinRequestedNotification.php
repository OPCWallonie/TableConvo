<?php

namespace App\Notifications\Company;

use App\Models\CompanyJoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyJoinRequestedNotification extends Notification implements ShouldQueue
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
        $requester = $this->joinRequest->user;
        $company   = $this->joinRequest->company;

        return (new MailMessage)
            ->subject("Demande d'adhésion à {$company->name}")
            ->greeting("Bonjour,")
            ->line("{$requester->full_name} ({$requester->email}) souhaite rejoindre la société **{$company->name}**.")
            ->when($this->joinRequest->message, fn ($mail) => $mail->line("Message : {$this->joinRequest->message}"))
            ->action('Gérer les membres', url('/espace/societe/membres'))
            ->line('Vous pouvez approuver ou rejeter cette demande depuis votre espace membre.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'company_join_requested',
            'join_request_id' => $this->joinRequest->id,
            'requester_name'  => $this->joinRequest->user->full_name,
            'requester_email' => $this->joinRequest->user->email,
            'company_id'      => $this->joinRequest->company_id,
            'company_name'    => $this->joinRequest->company->name,
        ];
    }
}
