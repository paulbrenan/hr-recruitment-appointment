<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an applicant who PASSED Qualification Checking but has not yet
 * been given an interview/exam/ranking schedule. Previously this case
 * reused QualificationResultNotification with $overridePassed = false,
 * which sent the full "Disqualified" branded template to a genuinely
 * qualified applicant -- misleading, since the qualification_result on
 * the record (and the criteria table rendered in that same email) still
 * correctly said "Qualified", contradicting the email's own header.
 *
 * This notification tells the truth: you qualified, a schedule just
 * hasn't been set yet.
 */
class QualifiedPendingScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $application = $this->application;

        return (new MailMessage)
            ->subject('DepEd Cavite – You Are Qualified, Schedule Pending (' . $application->transaction_number . ')')
            ->view('mail.qualified-pending-schedule', [
                'application' => $application,
                'candidate'   => $application->candidate,
                'jobPosting'  => $application->jobPosting,
            ]);
    }
}
