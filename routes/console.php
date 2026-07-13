<?php
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sends 24hr-before reminder emails for interview/ranking/exam schedules.
// Runs hourly; the command itself only sends reminders that haven't
// already gone out (reminder_sent_at is null), so it's safe to run
// this often without duplicate emails.
Schedule::command('schedules:send-reminders')->hourly();

// Auto-close job postings whose closes_at date has passed.
// Runs once a day at midnight; safe to run more often if needed.
Schedule::command('job-postings:close-expired')->dailyAt('00:00');
