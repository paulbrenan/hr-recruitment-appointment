<?php
/**
 * fix_auto_register_new_job_title.php
 *
 * store()'s title field is validated via Rule::in(config('job_titles.titles'))
 * -- typing anything not already in that list gets rejected. But
 * JobTitleRegistrar already exists (currently only used by the PDF
 * import pipeline) to add a new title to config/job_titles.php on disk
 * AND sync it into the in-memory config for the rest of the request.
 *
 * This wires that same registrar into manual job posting creation: if
 * the submitted title isn't already in the list, it's registered BEFORE
 * validation runs, so Rule::in() then passes against the now-updated
 * list instead of rejecting it.
 *
 * HOW TO RUN:
 *   php fix_auto_register_new_job_title.php   (from project root)
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

echo "\n=== fix_auto_register_new_job_title.php ===\n\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// ─── 1. Import JobTitleRegistrar ────────────────────────────────────────

echo "[1] Adding use statement for JobTitleRegistrar...\n";

apply_patch(
    $controllerPath,
    "use App\Models\Panelist;",
    "use App\Models\Panelist;\nuse App\Services\JobTitleRegistrar;",
    'JobPostingController: import JobTitleRegistrar'
);

// ─── 2. store(): auto-register the title before validating ─────────────

echo "\n[2] Patching store() to auto-register a new title before validation...\n";

apply_patch(
    $controllerPath,
    "    public function store(Request \$request)
    {
        \$validated = \$request->validate(\$this->rules(forCreate: true));",
    "    public function store(Request \$request)
    {
        // A title typed in that isn't already in config/job_titles.php
        // would otherwise fail Rule::in() below. Register it first (same
        // registrar the PDF import pipeline already uses) so a genuinely
        // new title is accepted and permanently added to the dropdown,
        // instead of being rejected.
        \$this->autoRegisterTitle(\$request);

        \$validated = \$request->validate(\$this->rules(forCreate: true));",
    'JobPostingController::store() -- auto-register new title before validation'
);

// ─── 3. Add the helper method ────────────────────────────────────────────

echo "\n[3] Adding autoRegisterTitle() helper...\n";

apply_patch(
    $controllerPath,
    '    private function rules(bool $forCreate = false): array
    {',
    '    /**
     * Registers a brand-new title (typed in, not picked from the
     * dropdown) into config/job_titles.php on disk, so it passes
     * Rule::in() validation and becomes a real permanent option for
     * every future posting -- same registrar the PDF import pipeline
     * already uses for this exact purpose.
     */
    private function autoRegisterTitle(\Illuminate\Http\Request $request): void
    {
        $title = trim((string) $request->input(\'title\', \'\'));

        if ($title === \'\') {
            return;
        }

        app(JobTitleRegistrar::class)->register($title);
    }

    private function rules(bool $forCreate = false): array
    {',
    'JobPostingController: add autoRegisterTitle() helper'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Creating a job posting with a title not already in\n";
echo "    config/job_titles.php now succeeds -- the title is registered\n";
echo "    (written to disk, permanently) BEFORE validation, instead of\n";
echo "    being rejected.\n";
echo "  - Uses the exact same JobTitleRegistrar the PDF import pipeline\n";
echo "    already relies on, so behavior (dedup, sort order, file format)\n";
echo "    stays consistent between both paths.\n\n";
echo "NOT changed yet: update() (editing an existing posting's title) --\n";
echo "I don't have its current exact source to patch confidently. If you\n";
echo "want editing-to-a-new-title to auto-register too, paste me\n";
echo "update()'s current body and I'll add the same one-line call there.\n\n";
echo "NOTE: this means ANY typed title gets added permanently, with no\n";
echo "review step -- a typo becomes a permanent dropdown entry too. If\n";
echo "you'd rather have new titles land in a pending/review list instead\n";
echo "of going live immediately, say so and I'll adjust.\n\n";
echo "DELETE this script after running.\n";
