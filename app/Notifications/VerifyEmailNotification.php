<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(config('auth.verification.expire', 60)), ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]);

        return (new MailMessage)->subject('Verifica tu cuenta')->view('emails.verify-email', ['user' => $notifiable, 'url' => $url]);
    }
}
