<?php

namespace App\Notifications;

use App\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleInvitationNotification extends Notification
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

    /**
     * Human-readable label for the schedule type, used in the subject
     * and greeting line. Falls back to the raw value for safety if a
     * new type is ever added without updating this map.
     */
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
        $candidate  = $this->schedule->application->candidate;
        $posting    = $this->schedule->application->jobPosting;
        $firstName  = $candidate->first_name;
        $typeLabel  = $this->typeLabel();
        $when       = $this->schedule->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("You're invited: {$typeLabel} â€” {$posting->title}")
            ->greeting("Hello, {$firstName},")
            ->line("You have been scheduled for the following **{$typeLabel}** as part of your application for **{$posting->title}**.")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        if ($this->schedule->interviewer_name) {
            $mail->line("**Interviewer/Panel:** {$this->schedule->interviewer_name}");
        }

        $mail->line('Please arrive at least 15 minutes early and bring any required documents.')
             ->action('View Job Posting', url("/job-postings/{$posting->id}"))
             ->line("We look forward to seeing you.")
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'type'        => $this->schedule->type,
            'scheduled_at' => $this->schedule->scheduled_at,
        ];
    }
}
