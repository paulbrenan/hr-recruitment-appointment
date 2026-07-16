<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a single panelist/interviewer, bundling every schedule
 * assignment they were given in one batch (possibly across several
 * candidates and several schedule types) into one email, instead of
 * one email per schedule.
 *
 * $assignments is a Collection of:
 *   ['schedule' => InterviewSchedule, 'candidate' => Candidate, 'jobPosting' => JobPosting]
 */
class PanelistScheduleBundleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Collection $assignments,
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
        // Group by candidate (application_id) so a panelist assigned to
        // the same candidate for multiple schedule types (Open Ranking +
        // Interview + Exam) sees ONE box for that candidate, with each
        // type listed as a sub-row -- instead of one box per type.
        $groups = $this->assignments
            ->sortBy(fn ($a) => $a['schedule']->scheduled_at)
            ->groupBy(fn ($a) => $a['schedule']->application_id)
            ->map(function ($assignments) {
                $first = $assignments->first();

                $items = $assignments
                    ->map(function ($a) {
                        return [
                            'type_label' => $this->typeLabel($a['schedule']->type),
                            'when'       => $a['schedule']->scheduled_at->format('l, F j, Y \a\t g:i A'),
                            'location'   => $a['schedule']->location,
                        ];
                    })
                    ->values();

                // If every schedule type for this candidate shares the
                // same date/time and location (the normal case -- all
                // created together in one batch), collapse into a
                // single line listing every applicable type instead of
                // repeating the same date/time/location per type.
                $uniqueSlots = $items->map(fn ($i) => $i['when'] . '|' . $i['location'])->unique();

                if ($uniqueSlots->count() === 1) {
                    return [
                        'candidate'   => $first['candidate']->full_name,
                        'position'    => $first['jobPosting']->title,
                        'combined'    => true,
                        'type_labels' => $items->pluck('type_label')->implode(', '),
                        'when'        => $items->first()['when'],
                        'location'    => $items->first()['location'],
                    ];
                }

                return [
                    'candidate' => $first['candidate']->full_name,
                    'position'  => $first['jobPosting']->title,
                    'combined'  => false,
                    'items'     => $items->all(),
                ];
            })
            ->values()
            ->all();

        $itemCount = $this->assignments->count();
        $candidateCount = count($groups);

        $subject = $candidateCount === 1
            ? "Schedule Assignment: {$groups[0]['candidate']} — {$groups[0]['position']}"
            : "Schedule Assignments ({$candidateCount} candidates) — DepEd Cavite Recruitment";

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.panelist-schedule-bundle', [
                'groups'     => $groups,
                'itemCount'  => $itemCount,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'schedule_ids' => $this->assignments->pluck('schedule.id')->all(),
        ];
    }
}