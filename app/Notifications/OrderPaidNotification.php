<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Commande #{$this->order->id} confirmée — TableConvo")
            ->greeting("Bonjour {$notifiable->first_name},")
            ->line("Votre commande #{$this->order->id} a été confirmée et votre facture vous a été envoyée par e-mail.")
            ->line("Montant TTC : " . number_format($this->order->total_ttc, 2, ',', ' ') . " €")
            ->action('Voir mes cartes', route('espace.cartes'))
            ->line('Merci de votre confiance !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_paid',
            'order_id' => $this->order->id,
            'total_ttc' => $this->order->total_ttc,
            'message' => "Votre commande #{$this->order->id} a été confirmée.",
        ];
    }
}
