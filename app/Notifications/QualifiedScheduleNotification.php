<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\InterviewSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by the Step 3 "Send all emails" bulk action to applicants who are
 * qualified AND have at least one interview/exam/open-ranking schedule.
 * Combines the same qualified-notice PDF as QualificationResultNotification
 * with the schedule details, in a single email.
 */
class QualifiedScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly InterviewSchedule $schedule,
    ) {}

    public function via($notifiable): array
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

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;
        $candidate   = $application->candidate;
        $posting     = $application->jobPosting;
        $criteriaRows = $this->buildCriteriaRows();
        $typeLabel   = $this->typeLabel();
        $when        = $this->schedule->scheduled_at->format('l, F j, Y \a\t g:i A');

        $pdf = Pdf::loadView('pdf.qualification-notice', [
            'application'  => $application,
            'candidate'    => $candidate,
            'jobPosting'   => $posting,
            'passed'       => true,
            'criteriaRows' => $criteriaRows,
            'check'        => $application->qualification_check,
        ])->setPaper('letter');

        $filename = 'Qualified-Notice-' . $application->transaction_number . '.pdf';

        $mail = (new MailMessage)
            ->subject("Congratulations! You're Qualified & Scheduled — {$posting->title}")
            ->greeting("Congratulations, {$candidate->first_name}!")
            ->line("We are pleased to inform you that you have **passed** the qualification screening for the **{$posting->title}** position. The official notice is attached to this email.")
            ->line("As the next step, you have been scheduled for the following **{$typeLabel}**:")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        $panelistNames = $this->schedule->panelists->pluck('name')->implode(', ');
        if ($panelistNames) {
            $mail->line("**Panelists:** {$panelistNames}");
        } elseif ($this->schedule->interviewer_name) {
            $mail->line("**Interviewer/Panel:** {$this->schedule->interviewer_name}");
        }

        $mail->line('Please arrive at least 15 minutes early and bring any required documents.')
             ->action('View Job Posting', url("/job-postings/{$posting->id}"))
             ->line('We look forward to seeing you!')
             ->salutation("Best regards,\nHR Recruitment Team")
             ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);

        return $mail;
    }

    public function toArray($notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'schedule_id'    => $this->schedule->id,
            'type'           => $this->schedule->type,
        ];
    }

    /** Same shape as QualificationResultNotification::buildCriteriaRows(). */
    private function buildCriteriaRows(): array
    {
        $jobPosting = $this->application->jobPosting;
        $criteria = $this->application->qualification_check['criteria'] ?? [];

        $map = [
            'education' => ['label' => 'Education', 'required' => $jobPosting->qualification_education ?? null],
            'experience' => ['label' => 'Experience', 'required' => $jobPosting->qualification_experience ?? null],
            'training' => ['label' => 'Training', 'required' => $jobPosting->qualification_training ?? null],
            'eligibility' => ['label' => 'Eligibility', 'required' => $jobPosting->qualification_eligibility ?? null],
        ];

        $rows = [];
        foreach ($map as $key => $meta) {
            $rows[] = [
                'label' => $meta['label'],
                'required' => $meta['required'],
                'actual' => $criteria[$key]['actual'] ?? null,
                'passed' => (bool) ($criteria[$key]['passed'] ?? false),
            ];
        }

        return $rows;
    }
}
