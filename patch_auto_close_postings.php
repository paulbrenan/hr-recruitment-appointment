<?php
/**
 * patch_auto_close_postings.php
 *
 * Adds:
 *   1. Auto-close: postings still open/interview_scheduled/ranking whose
 *      closes_at date has passed get flipped to 'closed' automatically
 *      (checked lazily on every index() and show() load -- no scheduler
 *      needed).
 *   2. "Closing soon" badge on the index table when closes_at is within
 *      the next 3 days and the posting isn't closed/archived yet.
 *
 * Run from your project root:
 *   php patch_auto_close_postings.php
 *
 * Creates .bak backups of both files before touching them. Aborts with
 * no changes made if any anchor string isn't found exactly as expected.
 */

function patch(string $path, array $replacements): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $content  = $original;

    foreach ($replacements as $i => [$search, $replace]) {
        $count = substr_count($content, $search);
        if ($count === 0) {
            fwrite(STDERR, "ABORT: anchor #$i not found in $path -- no changes written.\n");
            fwrite(STDERR, "Anchor was:\n---\n$search\n---\n");
            exit(1);
        }
        if ($count > 1) {
            fwrite(STDERR, "ABORT: anchor #$i matched $count times in $path (expected exactly 1) -- no changes written.\n");
            exit(1);
        }
        $content = str_replace($search, $replace, $content);
    }

    $backupPath = $path . '.bak';
    if (!file_exists($backupPath)) {
        copy($path, $backupPath);
        echo "Backed up: $backupPath\n";
    } else {
        echo "Backup already exists, skipping: $backupPath\n";
    }

    file_put_contents($path, $content);
    echo "Patched: $path\n";
}

// ─────────────────────────────────────────────────────────────────────────
// 1. JobPostingController.php
// ─────────────────────────────────────────────────────────────────────────
$controllerPath = 'app/Http/Controllers/JobPostingController.php';

patch($controllerPath, [
    // Insert the new auto-close method right before index(), and call it
    // at the top of index().
    [
        "    public function index(Request \$request)\n    {\n        // Archived postings are terminal/out-of-pipeline -- keep them out\n        // of the default list, toggle-able via ?archived=1.\n        \$showArchived = \$request->boolean('archived');",
        "    /**\n" .
        "     * Auto-close postings whose closing date has passed. Runs lazily on\n" .
        "     * every index() and show() load (no scheduler needed) -- any posting\n" .
        "     * still in the active pipeline (open / interview_scheduled / ranking)\n" .
        "     * with a closes_at strictly before today gets flipped to 'closed',\n" .
        "     * cascading to applications the same way the manual \"Close Posting\"\n" .
        "     * advance button does.\n" .
        "     */\n" .
        "    private function autoCloseExpiredPostings(): void\n" .
        "    {\n" .
        "        \$expired = JobPosting::whereIn('status', ['open', 'interview_scheduled', 'ranking'])\n" .
        "            ->whereNotNull('closes_at')\n" .
        "            ->whereDate('closes_at', '<', now()->toDateString())\n" .
        "            ->get();\n" .
        "\n" .
        "        foreach (\$expired as \$posting) {\n" .
        "            \$posting->update(['status' => 'closed']);\n" .
        "            \$this->cascadeStatusToApplications(\$posting, 'closed');\n" .
        "        }\n" .
        "    }\n" .
        "\n" .
        "    public function index(Request \$request)\n" .
        "    {\n" .
        "        \$this->autoCloseExpiredPostings();\n" .
        "\n" .
        "        // Archived postings are terminal/out-of-pipeline -- keep them out\n" .
        "        // of the default list, toggle-able via ?archived=1.\n" .
        "        \$showArchived = \$request->boolean('archived');",
    ],
    // Call it in show() too, before the posting is fetched, so opening a
    // stale posting's page also force-closes it.
    [
        "    public function show(\$id, \\Illuminate\\Http\\Request \$request)\n    {\n        \$posting   = JobPosting::with(['locations', 'panelists', 'assessmentCriteria'])->findOrFail(\$id);",
        "    public function show(\$id, \\Illuminate\\Http\\Request \$request)\n    {\n        \$this->autoCloseExpiredPostings();\n\n        \$posting   = JobPosting::with(['locations', 'panelists', 'assessmentCriteria'])->findOrFail(\$id);",
    ],
]);

// ─────────────────────────────────────────────────────────────────────────
// 2. index.blade.php -- "Closing soon" badge
// ─────────────────────────────────────────────────────────────────────────
$indexViewPath = 'resources/views/job-postings/index.blade.php';

patch($indexViewPath, [
    [
        "                    <td>{{ \$posting->closes_at ? \\Carbon\\Carbon::parse(\$posting->closes_at)->format('M d, Y') : '—' }}</td>",
        "                    <td>\n" .
        "                        @php\n" .
        "                            \$closingSoon = \$posting->closes_at\n" .
        "                                && !in_array(\$posting->status, ['closed', 'archived'])\n" .
        "                                && \\Carbon\\Carbon::parse(\$posting->closes_at)->isFuture()\n" .
        "                                && now()->diffInDays(\\Carbon\\Carbon::parse(\$posting->closes_at), false) <= 3;\n" .
        "                        @endphp\n" .
        "                        {{ \$posting->closes_at ? \\Carbon\\Carbon::parse(\$posting->closes_at)->format('M d, Y') : '—' }}\n" .
        "                        @if (\$closingSoon)\n" .
        "                            <br><span class=\"badge text-bg-warning\" style=\"font-size:0.65rem;\">Closing soon</span>\n" .
        "                        @endif\n" .
        "                    </td>",
    ],
]);

echo "\nDone. Postings past their closes_at will now auto-close on the next page load,\n";
echo "and ones closing within 3 days show a \"Closing soon\" badge in the index table.\n";
