<?php
/**
 * fix_register_excludes_archived.php
 *
 * CandidateAuthController::showRegister() built the position dropdown with
 * `where('status', '!=', 'closed')`, which was written back when 'closed'
 * was the only terminal status. Now that 'archived' also exists (and is
 * conceptually "closed" too -- it's always reached FROM closed), archived
 * postings were slipping through and appearing as selectable positions on
 * the public Online Recruitment Form.
 *
 * Fix: exclude both 'closed' and 'archived'.
 *
 * HOW TO RUN:
 *   php fix_register_excludes_archived.php   (from project root)
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

echo "\n=== fix_register_excludes_archived.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/CandidateAuthController.php';

echo "[1] Patching showRegister() to exclude archived postings too...\n";

apply_patch(
    $controllerPath,
    "        \$openPostings = JobPosting::where('status', '!=', 'closed')
            ->where(function (\$query) {
                \$query->whereNull('closes_at')
                      ->orWhereDate('closes_at', '>=', now()->toDateString());
            })
            ->with('locations')
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();",
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
    "CandidateAuthController: showRegister() excludes 'archived' as well as 'closed'"
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The public registration form's position dropdown no longer\n";
echo "    lists archived postings (previously only 'closed' was excluded).\n\n";
echo "NOTE: register() (form submit handler) already independently checks\n";
echo "\$jobPosting->status !== 'open' before accepting a submission, so\n";
echo "even if an archived posting's ID were submitted directly it would\n";
echo "already be rejected -- this patch only fixes what's SHOWN in the\n";
echo "dropdown.\n\n";
echo "DELETE this script after running.\n";
