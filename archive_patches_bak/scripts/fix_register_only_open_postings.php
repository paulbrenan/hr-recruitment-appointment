<?php
/**
 * fix_register_only_open_postings.php
 *
 * showRegister() was built as an EXCLUDE list -- first just != 'closed',
 * then whereNotIn(['closed','archived']) after the archive fix. Either
 * way, that still let 'interview_scheduled' and 'ranking' postings appear
 * as selectable positions on the public Online Recruitment Form -- even
 * though register() (the submit handler) only ever accepts a submission
 * when status === 'open', and silently rejects everything else with
 * "this position is no longer available." Candidates could pick a
 * ranking-stage posting from the dropdown and get bounced back with an
 * error after filling out the whole form.
 *
 * Fix: switch from an exclude-list to an include-list -- only 'open'
 * postings are ever shown, matching what register() actually accepts.
 *
 * HOW TO RUN:
 *   php fix_register_only_open_postings.php   (from project root)
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

echo "\n=== fix_register_only_open_postings.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/CandidateAuthController.php';

echo "[1] Patching showRegister() to only list status='open' postings...\n";

apply_patch(
    $controllerPath,
    "        \$openPostings = JobPosting::whereNotIn('status', ['closed', 'archived'])
            ->where(function (\$query) {
                \$query->whereNull('closes_at')
                      ->orWhereDate('closes_at', '>=', now()->toDateString());
            })
            ->with('locations')
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();",
    "        // Include-list, not exclude-list: register() only ever accepts a
        // submission when status === 'open', so anything else ('ranking',
        // 'interview_scheduled', 'closed', 'archived', ...) must not be
        // shown as a selectable position here either -- otherwise a
        // candidate can pick a stage-advanced posting from this dropdown
        // and get bounced back with an error after filling out the form.
        \$openPostings = JobPosting::where('status', 'open')
            ->where(function (\$query) {
                \$query->whereNull('closes_at')
                      ->orWhereDate('closes_at', '>=', now()->toDateString());
            })
            ->with('locations')
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();",
    "CandidateAuthController: showRegister() only lists status='open' postings"
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The registration form's position dropdown now shows ONLY\n";
echo "    postings with status exactly 'open' -- matching what register()\n";
echo "    actually accepts. Postings at any other stage (interview\n";
echo "    scheduled, ranking, closed, archived) no longer appear as\n";
echo "    selectable options.\n\n";
echo "DELETE this script after running.\n";
