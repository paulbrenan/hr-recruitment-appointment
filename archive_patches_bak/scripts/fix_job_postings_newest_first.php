<?php
/**
 * fix_job_postings_newest_first.php
 *
 * index() already used ->latest() (orderBy created_at desc), which
 * SHOULD put new postings at the top -- but new postings were showing
 * up at the bottom instead. Most likely cause: not every insert path
 * (PDF import, older one-off scripts run directly against the DB, etc.)
 * reliably sets created_at, so ties on that column get resolved by
 * whatever order the database feels like -- often effectively
 * insertion order, i.e. oldest-looking-first.
 *
 * Fix: sort by id descending instead. The primary key is guaranteed to
 * increase with every new row no matter how it was inserted, so this is
 * a hard guarantee of newest-first regardless of timestamp reliability.
 *
 * HOW TO RUN:
 *   php fix_job_postings_newest_first.php   (from project root)
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

echo "\n=== fix_job_postings_newest_first.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

echo "[1] Patching index() to sort by id descending...\n";

apply_patch(
    $controllerPath,
    "        \$postings = JobPosting::with('locations')
            ->when(\$showArchived, fn (\$q) => \$q->where('status', 'archived'))
            ->when(!\$showArchived, fn (\$q) => \$q->where('status', '!=', 'archived'))
            ->latest()
            ->get();",
    "        // Sort by id, not created_at -- not every insert path (PDF\n" .
    "        // import, one-off scripts run directly against the DB, etc.)\n" .
    "        // reliably sets created_at, which let new postings show up\n" .
    "        // out of order. id is guaranteed to increase with every new\n" .
    "        // row regardless of how it was inserted.\n" .
    "        \$postings = JobPosting::with('locations')\n" .
    "            ->when(\$showArchived, fn (\$q) => \$q->where('status', 'archived'))\n" .
    "            ->when(!\$showArchived, fn (\$q) => \$q->where('status', '!=', 'archived'))\n" .
    "            ->orderByDesc('id')\n" .
    "            ->get();",
    "JobPostingController::index() -- sort by id desc, newest posting always first"
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - The job postings list (/job-postings) now sorts by id\n";
echo "    descending -- a brand-new posting will always appear at the\n";
echo "    very top, guaranteed, regardless of what its created_at ended\n";
echo "    up being set to.\n\n";
echo "DELETE this script after running.\n";
