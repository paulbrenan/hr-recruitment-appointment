<?php
/**
 * fix_form_min_date.php
 *
 * Client-side companion to fix_no_past_dates_on_create.php. Adds a `min`
 * attribute to the Posted date / Closes date pickers in the job posting
 * form so past dates are greyed out in the browser -- but ONLY when
 * creating a new posting ($posting->exists is false). Editing an existing
 * posting leaves the picker unrestricted, since it may already have a
 * past date on it.
 *
 * HOW TO RUN:
 *   php fix_form_min_date.php   (from project root)
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

echo "\n=== fix_form_min_date.php ===\n\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

echo "[1] Adding min= to Posted date / Closes date pickers (create only)...\n";

apply_patch(
    $formPath,
    '                <div class="col-md-4">
                    <label class="form-label small fw-medium">Posted date</label>
                    <input type="date" class="form-control" name="posted_at" value="{{ old(\'posted_at\', optional($posting->posted_at ?? null)->format(\'Y-m-d\')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Closes</label>
                    <input type="date" class="form-control" name="closes_at" value="{{ old(\'closes_at\', optional($posting->closes_at ?? null)->format(\'Y-m-d\')) }}">
                </div>',
    '                <div class="col-md-4">
                    <label class="form-label small fw-medium">Posted date</label>
                    <input type="date" class="form-control" name="posted_at"
                           value="{{ old(\'posted_at\', optional($posting->posted_at ?? null)->format(\'Y-m-d\')) }}"
                           @if (!$posting->exists) min="{{ now()->format(\'Y-m-d\') }}" @endif>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-medium">Closes</label>
                    <input type="date" class="form-control" name="closes_at"
                           value="{{ old(\'closes_at\', optional($posting->closes_at ?? null)->format(\'Y-m-d\')) }}"
                           @if (!$posting->exists) min="{{ now()->format(\'Y-m-d\') }}" @endif>
                </div>',
    'form.blade.php: min date on posted_at/closes_at pickers when creating'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - On the CREATE form, both date pickers now grey out any date\n";
echo "    before today in the browser's native calendar widget.\n";
echo "  - On the EDIT form (\$posting->exists === true), no min is set --\n";
echo "    existing postings with past dates remain editable normally.\n\n";
echo "This is the browser-side companion to fix_no_past_dates_on_create.php\n";
echo "(server-side validation) -- run that one too if you haven't already.\n";
echo "DELETE this script after running.\n";
