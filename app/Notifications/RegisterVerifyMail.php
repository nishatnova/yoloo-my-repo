<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class RegisterVerifyMail extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
   
     public function via($notifiable)
     {
         return ['mail'];
     }
 
     public function toMail($notifiable)
     {
         $verificationUrl = URL::temporarySignedRoute(
             'verification.verify',
             now()->addMinutes(60),
             ['id' => $notifiable->getKey()]
         );
 
         return (new MailMessage)
             ->subject('Verify Your Email Address')
             ->line('Click the button below to verify your email address.')
             ->action('Verify Email', $verificationUrl)
             ->line('If you did not create an account, no further action is required.');
     }
}
