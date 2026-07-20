<?php
/**
 * fix_qualified_pending_schedule_notice.php
 *
 * sendAllScheduleNotices() had 3 real outcomes but only 2 templates:
 *   - qualified + scheduled     -> QualifiedScheduleNotification (correct)
 *   - not qualified             -> QualificationResultNotification (correct)
 *   - qualified, NOT scheduled  -> QualificationResultNotification forced
 *                                  to the DISQUALIFIED template via
 *                                  $overridePassed = false, while
 *                                  qualification_result stayed 'qualified'
 *                                  on the record. Result: the email header
 *                                  said "Disqualified" while the criteria
 *                                  table in that same email (built from
 *                                  the real, untouched data) correctly
 *                                  showed everything as Qualified --
 *                                  contradicting itself and confusing/
 *                                  alarming genuinely qualified applicants.
 *
 * Fix: that third case now sends QualifiedPendingScheduleNotification --
 * an honest, distinct email that says "you qualified, a schedule just
 * hasn't been set yet."
 *
 * REQUIRES, copied into place BEFORE running this:
 *   - app/Notifications/QualifiedPendingScheduleNotification.php
 *   - resources/views/mail/qualified-pending-schedule.blade.php
 *
 * HOW TO RUN:
 *   php fix_qualified_pending_schedule_notice.php   (from project root)
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

echo "\n=== fix_qualified_pending_schedule_notice.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/ApplicationController.php';

echo "[1] Adding use statement for the new notification...\n";

apply_patch(
    $controllerPath,
    "use App\Notifications\QualificationResultNotification;",
    "use App\Notifications\QualificationResultNotification;\nuse App\Notifications\QualifiedPendingScheduleNotification;",
    'ApplicationController: import QualifiedPendingScheduleNotification'
);

echo "\n[2] Patching sendAllScheduleNotices() branch logic...\n";

apply_patch(
    $controllerPath,
    "            try {
                if (\$isQualified && \$schedule) {
                    \$application->candidate->notify(new QualifiedScheduleNotification(\$application, \$schedule));
                } else {
                    // Not qualified, or qualified but never scheduled -> disqualification letter.
                    \$forcePassedFalse = (\$isQualified && !\$schedule) ? false : null;
                    \$application->candidate->notify(new QualificationResultNotification(\$application, \$forcePassedFalse));
                }

                \$application->update(['schedule_notice_sent_at' => now()]);
                \$sent++;
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::error('Bulk schedule notice failed for application ' . \$application->id . ': ' . \$e->getMessage());
            }",
    "            try {
                if (\$isQualified && \$schedule) {
                    \$application->candidate->notify(new QualifiedScheduleNotification(\$application, \$schedule));
                } elseif (\$isQualified && !\$schedule) {
                    // Qualified but no schedule yet -- honest, distinct
                    // notice. Previously this forced the DISQUALIFIED
                    // template via \$overridePassed = false, which
                    // contradicted the qualification_result (and the
                    // criteria table) that same email displayed.
                    \$application->candidate->notify(new QualifiedPendingScheduleNotification(\$application));
                } else {
                    // Genuinely not qualified.
                    \$application->candidate->notify(new QualificationResultNotification(\$application));
                }

                \$application->update(['schedule_notice_sent_at' => now()]);
                \$sent++;
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::error('Bulk schedule notice failed for application ' . \$application->id . ': ' . \$e->getMessage());
            }",
    'ApplicationController::sendAllScheduleNotices() -- honest notice for qualified-but-unscheduled'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Qualified applicants without a schedule now get an honest\n";
echo "    'You Are Qualified — Schedule Pending' email instead of being\n";
echo "    told they're disqualified.\n";
echo "  - Genuinely not-qualified applicants still get the disqualified\n";
echo "    template, unchanged.\n";
echo "  - QualificationResultNotification's \$overridePassed parameter is\n";
echo "    no longer called with false anywhere in this method -- it's\n";
echo "    left in the class as-is in case something else uses it, but\n";
echo "    say the word if you want it removed entirely now that nothing\n";
echo "    does.\n\n";
echo "DELETE this script after running.\n";
