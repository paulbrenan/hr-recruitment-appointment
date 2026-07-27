<?php
/**
 * fix_panelist_email_notifications.php
 *
 * Panelists selected on a schedule (1-6 via the checklist, panelist_ids)
 * were being synced to the schedule but NEVER emailed:
 *   - store() only emailed the legacy single interviewer_email field.
 *   - storeForPosting() -- the one the pipeline's "New schedule" modal
 *     actually calls -- had no panelist notification code at all.
 *   - The Panelist model/table had no email column to send to in the
 *     first place.
 *
 * This patch:
 *   1. Adds 'email' to Panelist's $fillable (run the accompanying
 *      migration FIRST: 2026_07_15_010000_add_email_to_panelists.php).
 *   2. store(): after syncing panelist_ids, emails every synced panelist
 *      who has an email on file (reuses InterviewerInvitationNotification,
 *      same class already used for the legacy single-interviewer field).
 *   3. storeForPosting(): same, for every schedule created in that bulk
 *      flow.
 *
 * HOW TO RUN:
 *   1. Copy 2026_07_15_010000_add_email_to_panelists.php into
 *      database/migrations/, then: php artisan migrate
 *   2. php fix_panelist_email_notifications.php   (from project root)
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

echo "\n=== fix_panelist_email_notifications.php ===\n\n";

$panelistPath   = ROOT . '/app/Models/Panelist.php';
$controllerPath = ROOT . '/app/Http/Controllers/InterviewScheduleController.php';

// ─── 1. Panelist model: email is fillable ──────────────────────────────

echo "[1] Adding 'email' to Panelist::\$fillable...\n";

apply_patch(
    $panelistPath,
    "    protected \$fillable = ['name'];",
    "    protected \$fillable = ['name', 'email'];",
    'Panelist: email fillable'
);

// ─── 2. store(): email every synced panelist ────────────────────────────

echo "\n[2] Patching store() to email every synced panelist...\n";

apply_patch(
    $controllerPath,
    "        if (\$schedule->interviewer_email) {
            try {
                Notification::route('mail', \$schedule->interviewer_email)
                    ->notify(new InterviewerInvitationNotification(\$schedule));
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send interviewer schedule invitation: ' . \$e->getMessage());
            }
        }

        return back()->with('success', 'Schedule created successfully. Invitation email sent.');
    }",
    "        if (\$schedule->interviewer_email) {
            try {
                Notification::route('mail', \$schedule->interviewer_email)
                    ->notify(new InterviewerInvitationNotification(\$schedule));
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send interviewer schedule invitation: ' . \$e->getMessage());
            }
        }

        // Email every panelist selected on the checklist (1-6), not just
        // the legacy single interviewer_email field above. Panelists
        // without an email on file are silently skipped rather than
        // failing the whole request.
        foreach (\$schedule->panelists as \$panelist) {
            if (empty(\$panelist->email)) {
                continue;
            }
            try {
                Notification::route('mail', \$panelist->email)
                    ->notify(new InterviewerInvitationNotification(\$schedule));
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::warning(\"Failed to send panelist schedule invitation to {\$panelist->email}: \" . \$e->getMessage());
            }
        }

        return back()->with('success', 'Schedule created successfully. Invitation email sent.');
    }",
    'InterviewScheduleController::store() -- emails synced panelists'
);

// ─── 3. storeForPosting(): email every synced panelist per schedule ────

echo "\n[3] Patching storeForPosting() to email every synced panelist...\n";

apply_patch(
    $controllerPath,
    "                if (!empty(\$panelistIds)) {
                    \$schedule->panelists()->sync(\$panelistIds);
                }

                // Send invitation to candidate (one per selected type)
                try {
                    \$application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification(\$schedule));
                } catch (\Throwable \$e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . \$e->getMessage());
                }

                \$created++;",
    "                if (!empty(\$panelistIds)) {
                    \$schedule->panelists()->sync(\$panelistIds);

                    // Email every panelist selected on the checklist (1-6).
                    // Skipped silently if a panelist has no email on file.
                    foreach (\$schedule->panelists as \$panelist) {
                        if (empty(\$panelist->email)) {
                            continue;
                        }
                        try {
                            \Illuminate\Support\Facades\Notification::route('mail', \$panelist->email)
                                ->notify(new \App\Notifications\InterviewerInvitationNotification(\$schedule));
                        } catch (\Throwable \$e) {
                            \Illuminate\Support\Facades\Log::warning(\"Failed to send panelist schedule invitation to {\$panelist->email}: \" . \$e->getMessage());
                        }
                    }
                }

                // Send invitation to candidate (one per selected type)
                try {
                    \$application->candidate->notify(new \App\Notifications\ScheduleInvitationNotification(\$schedule));
                } catch (\Throwable \$e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send schedule invitation: ' . \$e->getMessage());
                }

                \$created++;",
    'InterviewScheduleController::storeForPosting() -- emails synced panelists'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Panelist model can now store an email address.\n";
echo "  - Both store() (single-schedule modal) and storeForPosting() (bulk\n";
echo "    pipeline scheduling) now email every panelist attached via the\n";
echo "    checklist, reusing the existing InterviewerInvitationNotification\n";
echo "    class -- same one already used for the legacy single-interviewer\n";
echo "    field.\n\n";
echo "HEADS UP -- storeForPosting() creates one schedule PER applicant\n";
echo "(and per selected type), so if you schedule e.g. 5 qualified\n";
echo "applicants at once with the same 3 panelists checked, each panelist\n";
echo "gets 5 separate emails (one per schedule created), not 1. That\n";
echo "matches the existing per-schedule notification pattern already used\n";
echo "for candidates in this method -- say the word if you'd rather batch\n";
echo "panelist emails into one summary email per scheduling run instead.\n\n";
echo "DELETE this script after running.\n";
