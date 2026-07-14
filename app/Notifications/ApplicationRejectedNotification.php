<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;

        return (new MailMessage)
            ->subject('DepEd Cavite – Application Result (' . $application->transaction_number . ')')
            ->view('mail.rejected', [
                'application' => $application,
                'candidate'   => $application->candidate,
                'jobPosting'  => $application->jobPosting,
            ]);
    }
}
