<?php

namespace App\Notifications;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QualificationResultNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        // When null (default), "passed" is derived from qualification_result,
        // same as before. When explicitly false, the disqualification
        // template is sent even though qualification_result is 'qualified' --
        // used by the Step 3 "Send all emails" flow for applicants who
        // qualified but were never given a schedule, without altering their
        // saved qualification check result.
        public readonly ?bool $overridePassed = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;
        $passed = $this->overridePassed ?? ($application->qualification_result === 'qualified');
        $criteriaRows = $this->buildCriteriaRows();

        $subject = $passed
            ? 'DepEd Cavite – You Are Qualified (' . $application->transaction_number . ')'
            : 'DepEd Cavite – Qualification Result (' . $application->transaction_number . ')';

        $pdf = Pdf::loadView('pdf.qualification-notice', [
            'application' => $application,
            'candidate' => $application->candidate,
            'jobPosting' => $application->jobPosting,
            'passed' => $passed,
            'criteriaRows' => $criteriaRows,
            'check' => $application->qualification_check,
        ])->setPaper('letter');

        $filename = ($passed ? 'Qualified' : 'Disqualified') . '-Notice-' . $application->transaction_number . '.pdf';

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.qualification-result', [
                'application' => $application,
                'candidate' => $application->candidate,
                'jobPosting' => $application->jobPosting,
                'passed' => $passed,
                'criteriaRows' => $criteriaRows,
            ])
            ->attachData($pdf->output(), $filename, [
                'mime' => 'application/pdf',
            ]);
    }

    /**
     * Flatten qualification_check['criteria'] plus the job posting's
     * required QS text into the 4 rows the notice table needs, in the
     * fixed order the official template uses.
     */
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