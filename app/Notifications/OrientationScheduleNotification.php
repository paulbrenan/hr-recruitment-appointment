<?php

namespace App\Notifications;

use App\Models\OrientationSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrientationScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(public OrientationSchedule $orientation)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->orientation->application;
        $posting = $application?->jobPosting;
        $date = \Carbon\Carbon::parse($this->orientation->scheduled_at)->format('F j, Y (l)');
        $time = $this->orientation->scheduled_time
            ? \Carbon\Carbon::parse($this->orientation->scheduled_time)->format('g:i A')
            : null;

        $rawSg = $posting?->salary_grade;
        $salaryGrade = $rawSg
            ? (str_starts_with($rawSg, 'SG-') ? $rawSg : 'SG-' . $rawSg)
            : null;

        return (new MailMessage)
            ->subject('Orientation Schedule — ' . ($posting?->title ?? 'Your Application'))
            ->view('mail.orientation-schedule', [
                'candidateName'  => $notifiable->full_name ?? $notifiable->name ?? 'Applicant',
                'jobTitle'       => $posting?->title ?? 'N/A',
                'salaryGrade'    => $salaryGrade,
                'employmentType' => $posting?->employment_type ?? null,
                'date'           => $date,
                'time'           => $time,
                'place'          => $this->orientation->place ?? null,
            ]);
    }
}
