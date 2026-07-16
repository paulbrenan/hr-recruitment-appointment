<?php

namespace App\Notifications;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a qualified candidate who has one or more interview/exam/open
 * ranking schedules. Combines the qualified-notice PDF with EVERY
 * schedule currently on the application into a single email, instead
 * of one email per schedule type.
 */
class QualifiedScheduleBundleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly Collection $schedules,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'open_ranking' => 'Open Ranking Session',
            'interview'    => 'Interview',
            'exam'         => 'Examination',
            default        => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;
        $candidate   = $application->candidate;
        $jobPosting  = $application->jobPosting;
        $criteriaRows = $this->buildCriteriaRows();

        $scheduleRows = $this->buildScheduleRows();

        $pdf = Pdf::loadView('pdf.qualification-notice', [
            'application'  => $application,
            'candidate'    => $candidate,
            'jobPosting'   => $jobPosting,
            'passed'       => true,
            'criteriaRows' => $criteriaRows,
            'check'        => $application->qualification_check,
        ])->setPaper('letter');

        $filename = 'Qualified-Notice-' . $application->transaction_number . '.pdf';

        return (new MailMessage)
            ->subject("Congratulations! You're Qualified & Scheduled — {$jobPosting->title}")
            ->view('mail.qualified-schedule-bundle', [
                'application'  => $application,
                'candidate'    => $candidate,
                'jobPosting'   => $jobPosting,
                'criteriaRows' => $criteriaRows,
                'scheduleRows' => $scheduleRows,
            ])
            ->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
    }

    /**
     * Turn this application's schedules into the rows the email table
     * needs, merging any schedules that share the same date/time,
     * location, and panel into a single row (e.g. "Interview &
     * Examination") instead of listing them as separate sessions.
     */
    private function buildScheduleRows(): array
    {
        $panelKey = function ($schedule): string {
            $names = method_exists($schedule, 'panelists')
                ? $schedule->panelists->pluck('name')->sort()->values()->implode('|')
                : '';

            return $names !== '' ? $names : (string) $schedule->interviewer_name;
        };

        $groups = $this->schedules
            ->sortBy('scheduled_at')
            ->values()
            ->groupBy(function ($schedule) use ($panelKey) {
                // Same session = same timestamp + same location + same panel
                return $schedule->scheduled_at->toDateTimeString() . '|'
                    . $schedule->location . '|'
                    . $panelKey($schedule);
            });

        return $groups->map(function ($group) use ($panelKey) {
            $first = $group->first();
            $panelistNames = method_exists($first, 'panelists')
                ? $first->panelists->pluck('name')->implode(', ')
                : '';

            $typeLabels = $group->pluck('type')->unique()->map(fn ($type) => $this->typeLabel($type));

            return [
                'type_label' => $typeLabels->count() > 1
                    ? $typeLabels->slice(0, -1)->implode(', ') . ' & ' . $typeLabels->last()
                    : $typeLabels->first(),
                'when'       => $first->scheduled_at->format('l, F j, Y \a\t g:i A'),
                'location'   => $first->location,
                'panelists'  => $panelistNames ?: $first->interviewer_name,
            ];
        })->values()->all();
    }

    public function toArray($notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'schedule_ids'    => $this->schedules->pluck('id')->all(),
        ];
    }

    /** Same shape as QualificationResultNotification::buildCriteriaRows(). */
    private function buildCriteriaRows(): array
    {
        $jobPosting = $this->application->jobPosting;
        $criteria = $this->application->qualification_check['criteria'] ?? [];

        $map = [
            'education'   => ['label' => 'Education', 'required' => $jobPosting->qualification_education ?? null],
            'experience'  => ['label' => 'Experience', 'required' => $jobPosting->qualification_experience ?? null],
            'training'    => ['label' => 'Training', 'required' => $jobPosting->qualification_training ?? null],
            'eligibility' => ['label' => 'Eligibility', 'required' => $jobPosting->qualification_eligibility ?? null],
        ];

        $rows = [];
        foreach ($map as $key => $meta) {
            $rows[] = [
                'label'    => $meta['label'],
                'required' => $meta['required'],
                'actual'   => $criteria[$key]['actual'] ?? null,
                'passed'   => (bool) ($criteria[$key]['passed'] ?? false),
            ];
        }

        return $rows;
    }
}