<?php
/**
 * patch_fix_old_edu_year_undefined.php
 *
 * Fixes: ErrorException "Undefined variable $oldEduYear" on GET
 * /portal/register whenever there are currently no open job postings.
 *
 * Root cause: $oldEduYear (and $oldEduLevel / $oldEduCourse) were only
 * ever defined inside the @else branch of
 *   @if ($openPostingOptions->isEmpty()) ... @else ... @endif
 * i.e. only when the full registration form renders. But the <script>
 * block further down the page that does `@json($oldEduYear)` sits
 * outside that @if/@else entirely, so it always runs -- including on
 * the "No Openings Right Now" branch, where $oldEduYear was never set.
 *
 * Fix: hoist the old('education') parsing up above the @if, next to
 * the existing $openPostingOptions @php block, so the variables are
 * always defined regardless of which branch renders. The original
 * @php block inside the form is left in place (harmless -- it just
 * recomputes the same values), so nothing else has to change.
 *
 * Run once from the project root:
 *   php patch_fix_old_edu_year_undefined.php
 * Then delete this file — it is a one-shot installer, not idempotent.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$file = __DIR__ . '/resources/views/portal/register.blade.php';

$old = <<<'OLD'
  @php
    // Position dropdown: ONE entry per open job posting (title only --
    // place of assignment is picked separately in the dependent Place
    // field below, once a position is chosen). Uses $openPostings passed
    // in from the controller so we don't re-query the same data twice.
    $openPostingOptions = $openPostings
        ->sortBy('title')
        ->map(fn ($posting) => [
            'value' => $posting->id,
            'label' => $posting->title,
        ])
        ->values();
  @endphp

  @if ($openPostingOptions->isEmpty())
OLD;

$new = <<<'NEW'
  @php
    // Position dropdown: ONE entry per open job posting (title only --
    // place of assignment is picked separately in the dependent Place
    // field below, once a position is chosen). Uses $openPostings passed
    // in from the controller so we don't re-query the same data twice.
    $openPostingOptions = $openPostings
        ->sortBy('title')
        ->map(fn ($posting) => [
            'value' => $posting->id,
            'label' => $posting->title,
        ])
        ->values();

    // Hoisted above the @if below: the <script> block near the bottom of
    // this page reads $oldEduYear via @json() unconditionally, but the
    // form (and the @php block that used to be the only place these were
    // set) only renders in the @else branch. Compute them here too so
    // they're always defined, even on the "No Openings Right Now" branch.
    $oldEduParts  = old('education') ? array_map('trim', explode(' - ', old('education'), 3)) : [];
    $oldEduLevel  = $oldEduParts[0] ?? null;
    $oldEduCourse = $oldEduParts[1] ?? null;
    $oldEduYear   = $oldEduParts[2] ?? null;
  @endphp

  @if ($openPostingOptions->isEmpty())
NEW;

apply_patch($file, $old, $new, 'Hoist $oldEduYear/$oldEduLevel/$oldEduCourse above the @if so they are always defined');

echo "\nDone. Visiting /portal/register with zero open postings should no longer 500.\n";
