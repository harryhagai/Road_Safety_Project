<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RsrsResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 30);

        return (new MailMessage)
            ->subject('RSRS Password Reset Link')
            ->greeting('Hello'.($notifiable->name ? ' '.$notifiable->name : '').',')
            ->line('We received a request to reset the password for your Road Safety Reporting System account.')
            ->line('Use the secure link below to create a new password and regain access to RSRS.')
            ->action('Reset RSRS Password', $resetUrl)
            ->line("This RSRS password reset link will expire in {$expireMinutes} minutes.")
            ->line('If you did not request this reset, ignore this email and your current password will remain unchanged.')
            ->salutation('Road Safety Reporting System');
    }
}
