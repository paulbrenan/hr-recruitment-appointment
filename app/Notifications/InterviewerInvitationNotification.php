<?php

namespace App\Notifications;

use App\Models\InterviewSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the interviewer/panelist listed on a schedule.
 *
 * Interviewers aren't a model in this system (just interviewer_name /
 * interviewer_email strings on InterviewSchedule), so this is dispatched
 * with Notification::route('mail', $email) rather than ->notify() on
 * an Eloquent model. See InterviewScheduleController@store.
 */
class InterviewerInvitationNotification extends Notification
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
        $candidate = $this->schedule->application->candidate;
        $posting   = $this->schedule->application->jobPosting;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Schedule Assignment: {$typeLabel} â€” {$posting->title}")
            ->greeting("Hello,")
            ->line("You have been assigned to conduct the following **{$typeLabel}** for the **{$posting->title}** position.")
            ->line("**Candidate:** {$candidate->full_name}")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        $mail->line('Please confirm your availability with HR if there is any conflict.')
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
