<?php
/**
 * Patch: fix "Unclosed '{' on line 15" ParseError in ApplicationController.php
 *
 * The class-opening brace on line 15 is never closed. The file currently
 * ends right after sendAllScheduleNotices()'s closing brace, with no
 * final brace for the class itself. This script verifies the exact
 * expected tail before touching anything, backs up the file, then
 * appends the missing closing brace.
 */

$file = __DIR__ . '/app/Http/Controllers/ApplicationController.php';

if (!file_exists($file)) {
    fwrite(STDERR, "ABORT: File not found: $file\n");
    exit(1);
}

$content = file_get_contents($file);

// Exact expected tail (end of sendAllScheduleNotices method), used as an
// anchor so this patch only applies if the file is in the state we expect.
$anchor = <<<'PHP'
        return redirect()
            ->route('job-postings.show', ['id' => $jobPostingId, 'step' => 3])
            ->with('success', "Emailed {$sent} applicant(s).");
    }
PHP;

// Trim trailing whitespace/newlines for a reliable comparison of the tail.
$trimmed = rtrim($content);

if (!str_ends_with($trimmed, $anchor)) {
    fwrite(STDERR, "ABORT: File does not end with the expected anchor text. Aborting to avoid corrupting the file.\n");
    fwrite(STDERR, "Expected tail:\n$anchor\n");
    fwrite(STDERR, "Actual tail (last 500 chars):\n" . substr($trimmed, -500) . "\n");
    exit(1);
}

// Guard: make sure we're not accidentally adding a second closing brace if
// one somehow already exists right after the anchor (defensive check).
if (preg_match('/\}\s*\}\s*$/', $trimmed)) {
    fwrite(STDERR, "ABORT: File already appears to have two closing braces at the end. No changes made.\n");
    exit(1);
}

$backup = $file . '.bak';
if (!copy($file, $backup)) {
    fwrite(STDERR, "ABORT: Could not create backup at $backup\n");
    exit(1);
}
echo "Backup created: $backup\n";

$newContent = $trimmed . "\n}\n";

if (file_put_contents($file, $newContent) === false) {
    fwrite(STDERR, "ABORT: Failed to write updated file.\n");
    exit(1);
}

echo "SUCCESS: Added missing closing brace for class ApplicationController.\n";
echo "File updated: $file\n";
