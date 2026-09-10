<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    protected $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    protected function resetUrl($notifiable)
{
    $frontendUrl = Config::get('app.frontend_url'); // pega da config
    return "{$frontendUrl}/reset-password/{$this->token}?email=" . urlencode($notifiable->getEmailForPasswordReset());
}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Redefinição de Senha')
        ->markdown('emails.auth.reset-password', [
            'url' => $this->resetUrl($notifiable),
            'user' => $notifiable,
        ]);
}

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
