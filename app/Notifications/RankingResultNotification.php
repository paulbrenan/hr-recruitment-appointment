<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RankingResultNotification extends Notification
{
    use Queueable;

    public array $ranked;
    public JobPosting $posting;

    public function __construct(array $ranked, JobPosting $posting)
    {
        $this->ranked  = $ranked;
        $this->posting = $posting;
    }

    /**
     * Deliver via email and SMS (vonage/twilio).
     * Remove 'vonage' if you are not using SMS yet.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // -------------------------------------------------------------------------
    // EMAIL
    // -------------------------------------------------------------------------

    public function toMail(object $notifiable): MailMessage
    {
        $candidate  = $this->ranked['candidate'];
        $firstName  = $candidate->first_name;
        $rank       = $this->ranked['rank'];
        $total      = $this->ranked['total'];
        $score      = $this->ranked['weighted_score'];
        $passed     = $this->ranked['passed'];
        $title      = $this->posting->title;

        $subject = $passed
            ? "Congratulations! You ranked #{$rank} — {$title}"
            : "Application result — {$title}";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($passed ? "Congratulations, {$firstName}!" : "Hello, {$firstName},");

        if ($passed) {
            $mail->line("We are pleased to inform you that you have **passed** the initial screening for the **{$title}** position.")
                 ->line("**Your ranking:** #{$rank} out of {$total} applicants")
                 ->line("**Your weighted score:** {$score} / 100")
                 ->line("Our HR team will be in touch shortly to coordinate the next steps in the selection process.")
                 ->action('View Job Posting', url("/job-postings/{$this->posting->id}"))
                 ->line("We look forward to speaking with you!");
        } else {
            $mail->line("Thank you for your interest in the **{$title}** position and for taking the time to go through our screening process.")
                 ->line("After careful evaluation, we regret to inform you that you were not selected to move forward at this time.")
                 ->line("**Your ranking:** #{$rank} out of {$total} applicants")
                 ->line("**Your weighted score:** {$score} / 100")
                 ->line("We encourage you to keep an eye on our future job openings and apply again.")
                 ->action('View Other Openings', url('/job-postings'))
                 ->line("We wish you all the best in your career journey.");
        }

        $mail->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'job_posting_id' => $this->posting->id,
            'rank'           => $this->ranked['rank'],
            'score'          => $this->ranked['weighted_score'],
            'passed'         => $this->ranked['passed'],
        ];
    }
}