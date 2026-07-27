<?php
/**
 * Patch: remove the "View Job Posting" button and the "arrive 15
 * minutes early" line from the qualified-schedule-bundle candidate
 * email (mail.qualified-schedule-bundle).
 *
 * Run from your project root: php patch_remove_bundle_button_and_arrive_line.php
 */

$root = __DIR__;
$viewFile = $root . '/resources/views/mail/qualified-schedule-bundle.blade.php';

if (!file_exists($viewFile)) {
    fwrite(STDERR, "ABORT: file not found: $viewFile\n");
    fwrite(STDERR, "Edit the path variable at the top of this script if your project layout differs.\n");
    exit(1);
}

$backup = $viewFile . '.bak';
if (!copy($viewFile, $backup)) {
    fwrite(STDERR, "Failed to create backup at $backup\n");
    exit(1);
}

$content = file_get_contents($viewFile);

$old = <<<'BLADE'
    <div class="btn-wrap">
      <a href="{{ url('/job-postings/' . $jobPosting->id) }}" class="btn">View Job Posting</a>
    </div>

    <p>Please arrive at least 15 minutes early to each schedule above and bring any required documents.</p>

    <div class="note">
BLADE;

$new = <<<'BLADE'
    <div class="note">
BLADE;

if (strpos($content, $old) === false) {
    fwrite(STDERR, "ABORT: anchor not found in $viewFile. No changes written.\n");
    exit(1);
}
if (substr_count($content, $old) > 1) {
    fwrite(STDERR, "ABORT: anchor found more than once -- refusing to guess. No changes written.\n");
    exit(1);
}

$content = str_replace($old, $new, $content);
file_put_contents($viewFile, $content);

echo "Patched: $viewFile\n";
echo "Backup saved at: $backup\n";
