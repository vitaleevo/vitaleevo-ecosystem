<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reference,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/').'/pedido';

        return (new MailMessage)
            ->subject('Atualização do pedido '.$this->reference)
            ->greeting('Atualização do seu pedido')
            ->line('O pedido '.$this->reference.' está agora no estado: '.$this->status.'.')
            ->action('Acompanhar pedido', $url)
            ->line('Utilize a referência e o mesmo telefone usado no pedido para consultar o estado.');
    }
}
