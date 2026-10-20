<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $token,
        private readonly string $role,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/').'/?invite='.urlencode($this->token);

        return (new MailMessage)
            ->subject('Convite para '.$this->tenantName.' no Vitaleevo')
            ->greeting('Olá!')
            ->line('Foi convidado para participar em '.$this->tenantName.' com o papel de '.$this->role.'.')
            ->action('Aceitar convite', $url)
            ->line('Este convite expira em 72 horas.');
    }
}
