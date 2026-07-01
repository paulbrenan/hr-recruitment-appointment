<?php

namespace App\Console\Commands;

use App\Models\InterviewSchedule;
use App\Notifications\ScheduleReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:send-reminders';

    protected $description = 'Send a reminder email to candidates and interviewers for schedules happening in ~24 hours';

    public function handle(): int
    {
        // "~24 hours out" = scheduled_at falls between now+23h and now+25h.
        // Using a 2-hour window instead of an exact 24h mark means this
        // still catches the reminder even if the hourly scheduler run is
        // occasionally a few minutes late or the server was briefly down.
        $windowStart = now()->addHours(23);
        $windowEnd   = now()->addHours(25);

        $schedules = InterviewSchedule::with(['application.candidate', 'application.jobPosting'])
            ->whereNull('reminder_sent_at')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules due for a reminder right now.');
            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $schedule->application->candidate->notify(new ScheduleReminderNotification($schedule));

            if ($schedule->interviewer_email) {
                Notification::route('mail', $schedule->interviewer_email)
                    ->notify(new ScheduleReminderNotification($schedule));
            }

            $schedule->update(['reminder_sent_at' => now()]);

            $this->info("Reminder sent for schedule #{$schedule->id} ({$schedule->application->candidate->full_name}).");
        }

        return self::SUCCESS;
    }
}
