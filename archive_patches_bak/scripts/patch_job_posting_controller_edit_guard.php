<?php
/**
 * Patch: JobPostingController — reject edit()/update() at the route
 * level once a posting's status is no longer 'open', matching the UI
 * gate already added to the index page. Without this, someone hitting
 * /job-postings/{id}/edit directly by URL (or POSTing to update)
 * bypasses the button entirely.
 *
 * Run once from the project root:
 *   php patch_job_posting_controller_edit_guard.php
 * Then delete this file.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust this path if your project layout differs ────────────────────
$file = __DIR__ . '/app/Http/Controllers/JobPostingController.php';

apply_patch($file, [
    [
        <<<'OLD'
    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->exists = true;
OLD,
        <<<'NEW'
    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);

        if ($posting->status !== 'open') {
            return redirect()
                ->route('job-postings.index')
                ->with('error', 'This posting can no longer be edited once it\'s no longer open.');
        }

        $posting->exists = true;
NEW,
        'edit(): reject non-open postings',
    ],
    [
        <<<'OLD'
    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        $validated = $request->validate($this->rules());
OLD,
        <<<'NEW'
    public function update(Request $request, $id)
    {
        $posting = JobPosting::findOrFail($id);

        if ($posting->status !== 'open') {
            return redirect()
                ->route('job-postings.index')
                ->with('error', 'This posting can no longer be edited once it\'s no longer open.');
        }

        $validated = $request->validate($this->rules());
NEW,
        'update(): reject non-open postings',
    ],
]);

echo "\nDone. This uses the 'error' session key for the flash message -- check your\n";
echo "layout/index view renders session('error') (it currently only shows\n";
echo "session('success')); add an alert-danger block for it if not.\n";
echo "Diff and test before deleting this script and its .bak backup.\n";
