<?php
/**
 * fix_restyle_notifications.php
 *
 * Rewires ScheduleInvitationNotification, InterviewerInvitationNotification,
 * and RankingResultWithScheduleNotification to render through the new
 * DepEd-branded Blade views (resources/views/emails/*.blade.php) instead
 * of Laravel's default markdown line()/action() chain -- matching the
 * styling already used for the registration confirmation email and
 * QualificationResultNotification.
 *
 * REQUIRES these 3 view files copied into resources/views/emails/ FIRST:
 *   - schedule-invitation.blade.php
 *   - interviewer-invitation.blade.php
 *   - ranking-result-schedule.blade.php
 *
 * HOW TO RUN:
 *   1. Copy the 3 .blade.php files into resources/views/emails/
 *   2. php fix_restyle_notifications.php   (from project root)
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) { echo "\n❌ File not found: $path\n"; exit(1); }
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) {
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\n";
        exit(1);
    }
    if ($count > 1) {
        echo "\n❌ PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label\n";
        exit(1);
    }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

echo "\n=== fix_restyle_notifications.php ===\n\n";

$schedulePath  = ROOT . '/app/Notifications/ScheduleInvitationNotification.php';
$panelistPath  = ROOT . '/app/Notifications/InterviewerInvitationNotification.php';
$rankingPath   = ROOT . '/app/Notifications/RankingResultWithScheduleNotification.php';

// ─── 1. ScheduleInvitationNotification ──────────────────────────────────

echo "[1] Patching ScheduleInvitationNotification::toMail()...\n";

apply_patch(
    $schedulePath,
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate  = $this->schedule->application->candidate;
        $posting    = $this->schedule->application->jobPosting;
        $firstName  = $candidate->first_name;
        $typeLabel  = $this->typeLabel();
        $when       = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

        $mail = (new MailMessage)
            ->subject("You\'re invited: {$typeLabel} â€” {$posting->title}")
            ->greeting("Hello, {$firstName},")
            ->line("You have been scheduled for the following **{$typeLabel}** as part of your application for **{$posting->title}**.")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        if ($this->schedule->interviewer_name) {
            $mail->line("**Interviewer/Panel:** {$this->schedule->interviewer_name}");
        }

        $mail->line(\'Please arrive at least 15 minutes early and bring any required documents.\')
             ->action(\'View Job Posting\', url("/job-postings/{$posting->id}"))
             ->line("We look forward to seeing you.")
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }',
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate  = $this->schedule->application->candidate;
        $posting    = $this->schedule->application->jobPosting;
        $typeLabel  = $this->typeLabel();
        $when       = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

        return (new MailMessage)
            ->subject("You\'re Invited: {$typeLabel} — {$posting->title}")
            ->view(\'emails.schedule-invitation\', [
                \'candidate\'  => $candidate,
                \'jobPosting\' => $posting,
                \'schedule\'   => $this->schedule,
                \'typeLabel\'  => $typeLabel,
                \'when\'       => $when,
            ]);
    }',
    'ScheduleInvitationNotification: uses emails.schedule-invitation view'
);

// ─── 2. InterviewerInvitationNotification ───────────────────────────────

echo "\n[2] Patching InterviewerInvitationNotification::toMail()...\n";

apply_patch(
    $panelistPath,
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->schedule->application->candidate;
        $posting   = $this->schedule->application->jobPosting;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

        $mail = (new MailMessage)
            ->subject("Schedule Assignment: {$typeLabel} â€” {$posting->title}")
            ->greeting("Hello,")
            ->line("You have been assigned to conduct the following **{$typeLabel}** for the **{$posting->title}** position.")
            ->line("**Candidate:** {$candidate->full_name}")
            ->line("**Date & Time:** {$when}");

        if ($this->schedule->location) {
            $mail->line("**Location:** {$this->schedule->location}");
        }

        $mail->line(\'Please confirm your availability with HR if there is any conflict.\')
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }',
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->schedule->application->candidate;
        $posting   = $this->schedule->application->jobPosting;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

        return (new MailMessage)
            ->subject("Schedule Assignment: {$typeLabel} — {$posting->title}")
            ->view(\'emails.interviewer-invitation\', [
                \'candidate\'  => $candidate,
                \'jobPosting\' => $posting,
                \'schedule\'   => $this->schedule,
                \'typeLabel\'  => $typeLabel,
                \'when\'       => $when,
            ]);
    }',
    'InterviewerInvitationNotification: uses emails.interviewer-invitation view'
);

// ─── 3. RankingResultWithScheduleNotification ───────────────────────────

echo "\n[3] Patching RankingResultWithScheduleNotification::toMail()...\n";

apply_patch(
    $rankingPath,
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->ranked[\'candidate\'];
        $firstName = $candidate->first_name;
        $rank      = $this->ranked[\'rank\'];
        $total     = $this->ranked[\'total\'];
        $score     = $this->ranked[\'weighted_score\'];
        $title     = $this->posting->title;
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

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

        $mail->line(\'Please arrive at least 15 minutes early and bring any required documents.\')
             ->action(\'View Job Posting\', url("/job-postings/{$this->posting->id}"))
             ->line(\'We look forward to seeing you!\')
             ->salutation("Best regards,\nHR Recruitment Team");

        return $mail;
    }',
    '    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->ranked[\'candidate\'];
        $rank      = $this->ranked[\'rank\'];
        $total     = $this->ranked[\'total\'];
        $score     = $this->ranked[\'weighted_score\'];
        $typeLabel = $this->typeLabel();
        $when      = $this->schedule->scheduled_at->format(\'l, F j, Y \a\t g:i A\');

        return (new MailMessage)
            ->subject("Congratulations! You Ranked #{$rank} — {$this->posting->title}")
            ->view(\'emails.ranking-result-schedule\', [
                \'candidate\' => $candidate,
                \'posting\'   => $this->posting,
                \'schedule\'  => $this->schedule,
                \'rank\'      => $rank,
                \'total\'     => $total,
                \'score\'     => $score,
                \'typeLabel\' => $typeLabel,
                \'when\'      => $when,
            ]);
    }',
    'RankingResultWithScheduleNotification: uses emails.ranking-result-schedule view'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - All 3 notifications now render through DepEd-branded HTML views\n";
echo "    (header gradient, gold divider, transaction-style summary box,\n";
echo "    detail rows, note box, footer) instead of Laravel's default\n";
echo "    markdown notification template.\n\n";
echo "STILL PENDING: mail.qualification-result.blade.php -- send me that\n";
echo "file and I'll restyle it to match too, without losing its criteria\n";
echo "table logic.\n\n";
echo "DELETE this script after running.\n";
