<?php

namespace App\Notifications;

use App\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleReminderNotification extends Notification
{
    use Queueable;

    public InterviewSchedule $schedule;

    public function __construct(InterviewSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function typeLabel(): string
    {
        return match ($this->schedule->type) {
            'open_ranking' => 'Open Ranking Session',
            'interview'    => 'Interview',
            'exam'         => 'Examination',
            default        => ucfirst(str_replace('_', ' ', $this->schedule->type)),
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $posting   = $this->schedule->application->jobPosting;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Reminder: {$typeLabel} tomorrow â€” {$posting->title}")
            ->greeting('Hello,')
            ->line("This is a friendly reminder that your **{$typeLabel}** for **{$posting->title}** is coming up in less than 24 hours.")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        if ($this->schedule->interviewer_name) {
            $mail->line("**Interviewer/Panel:** {$this->schedule->interviewer_name}");
        }

        $mail->line('Please make sure to arrive on time and bring any required documents.')
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'type'        => $this->schedule->type,
        ];
    }
}
