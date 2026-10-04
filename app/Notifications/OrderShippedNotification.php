<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu pedido NOIR DISTRICT fue enviado')
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu pedido {$this->order->provider_reference} ya fue enviado.")
            ->line("Transportadora: {$this->order->shipping_carrier}")
            ->line("Guía: {$this->order->tracking_number}");
    }
}
