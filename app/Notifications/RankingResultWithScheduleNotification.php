<?php

namespace App\Notifications;

use App\Models\InterviewSchedule;
use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RankingResultWithScheduleNotification extends Notification
{
    use Queueable;

    public array $ranked;
    public JobPosting $posting;
    public InterviewSchedule $schedule;

    public function __construct(array $ranked, JobPosting $posting, InterviewSchedule $schedule)
    {
        $this->ranked   = $ranked;
        $this->posting  = $posting;
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
        $candidate = $this->ranked['candidate'];
        $firstName = $candidate->first_name;
        $rank      = $this->ranked['rank'];
        $total     = $this->ranked['total'];
        $score     = $this->ranked['weighted_score'];
        $title     = $this->posting->title;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Congratulations! You ranked #{$rank} - {$title}")
            ->greeting("Congratulations, {$firstName}!")
            ->line("We are pleased to inform you that you have **passed** the initial screening for the **{$title}** position.")
            ->line("**Your ranking:** #{$rank} out of {$total} applicants")
            ->line("**Your weighted score:** {$score} / 100")
            ->line("As the next step, you have been scheduled for the following **{$typeLabel}**:")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        if ($this->schedule->interviewer_name) {
            $mail->line("**Interviewer/Panel:** {$this->schedule->interviewer_name}");
        }

        $mail->line('Please arrive at least 15 minutes early and bring any required documents.')
             ->action('View Job Posting', url("/job-postings/{$this->posting->id}"))
             ->line('We look forward to seeing you!')
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_posting_id' => $this->posting->id,
            'rank'           => $this->ranked['rank'],
            'score'          => $this->ranked['weighted_score'],
            'schedule_id'    => $this->schedule->id,
        ];
    }
}
