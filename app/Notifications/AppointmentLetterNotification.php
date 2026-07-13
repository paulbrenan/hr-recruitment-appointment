<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentLetterNotification extends Notification
{
    use Queueable;

    public Appointment $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // -------------------------------------------------------------------------
    // EMAIL
    // -------------------------------------------------------------------------

    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->appointment->application->candidate;
        $firstName = $candidate->first_name;
        $position  = $this->appointment->position_title;
        $status    = str_replace('_', ' ', ucfirst($this->appointment->appointment_status));

        $mail = (new MailMessage)
            ->subject("Notice of Appointment — {$position}")
            ->greeting("Congratulations, {$firstName}!")
            ->line("You have been formally appointed to the position of **{$position}**.")
            ->line("**Appointment status:** {$status}");

        if ($this->appointment->item_number) {
            $mail->line("**Item number:** {$this->appointment->item_number}");
        }

        if ($this->appointment->appointment_date) {
            $date = \Carbon\Carbon::parse($this->appointment->appointment_date)->format('F d, Y');
            $mail->line("**Appointment date:** {$date}");
        }

        if ($this->appointment->onboarding_date) {
            $onboarding = \Carbon\Carbon::parse($this->appointment->onboarding_date)->format('F d, Y');
            $mail->line("**Onboarding date:** {$onboarding}");
        }

        $mail->line("Please keep this notice for your records. Our HR team will follow up shortly with onboarding instructions.")
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'application_id' => $this->appointment->application_id,
            'position_title' => $this->appointment->position_title,
        ];
    }
}
