<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as Base;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Base
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = (int) config('auth.passwords.users.expire', 60);
        $expiresIn = $minutes % 60 === 0 ? ($minutes / 60).' hours' : $minutes.' minutes';

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $url,
                'expiresIn' => $expiresIn,
            ]);
    }
}
