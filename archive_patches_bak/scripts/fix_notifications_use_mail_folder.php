<?php
/**
 * fix_notifications_use_mail_folder.php
 *
 * fix_restyle_notifications.php pointed the 3 notifications at
 * 'emails.schedule-invitation' etc. -- but this project already uses
 * resources/views/mail/ as its convention (that's where
 * qualification-result.blade.php lives), not resources/views/emails/.
 * Retargets the view() calls to 'mail.*' instead.
 *
 * REQUIRES the 3 view files placed in resources/views/mail/ (NOT
 * resources/views/emails/) -- same 3 files from before, just a different
 * folder:
 *   - schedule-invitation.blade.php
 *   - interviewer-invitation.blade.php
 *   - ranking-result-schedule.blade.php
 *
 * HOW TO RUN:
 *   1. Copy the 3 .blade.php files into resources/views/mail/
 *   2. php fix_notifications_use_mail_folder.php   (from project root)
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

echo "\n=== fix_notifications_use_mail_folder.php ===\n\n";

$schedulePath = ROOT . '/app/Notifications/ScheduleInvitationNotification.php';
$panelistPath = ROOT . '/app/Notifications/InterviewerInvitationNotification.php';
$rankingPath  = ROOT . '/app/Notifications/RankingResultWithScheduleNotification.php';

echo "[1] Retargeting ScheduleInvitationNotification to mail.schedule-invitation...\n";
apply_patch(
    $schedulePath,
    "->view('emails.schedule-invitation', [",
    "->view('mail.schedule-invitation', [",
    'ScheduleInvitationNotification: view path -> mail.schedule-invitation'
);

echo "\n[2] Retargeting InterviewerInvitationNotification to mail.interviewer-invitation...\n";
apply_patch(
    $panelistPath,
    "->view('emails.interviewer-invitation', [",
    "->view('mail.interviewer-invitation', [",
    'InterviewerInvitationNotification: view path -> mail.interviewer-invitation'
);

echo "\n[3] Retargeting RankingResultWithScheduleNotification to mail.ranking-result-schedule...\n";
apply_patch(
    $rankingPath,
    "->view('emails.ranking-result-schedule', [",
    "->view('mail.ranking-result-schedule', [",
    'RankingResultWithScheduleNotification: view path -> mail.ranking-result-schedule'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - All 3 notifications now point at resources/views/mail/ instead\n";
echo "    of resources/views/emails/, matching where\n";
echo "    qualification-result.blade.php already lives.\n\n";
echo "REMINDER: place the 3 .blade.php files in resources/views/mail/\n";
echo "(not emails/) before testing.\n\n";
echo "DELETE this script after running.\n";
