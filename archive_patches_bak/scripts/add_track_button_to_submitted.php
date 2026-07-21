<?php
/**
 * add_track_button_to_submitted.php
 *
 * One-shot script: patches resources/views/portal/submitted.blade.php
 * to add a "Track Your Application" button below the transaction number box,
 * linking to the public tracking page (route '/') with the transaction number
 * pre-filled via query string so the applicant lands directly on their result.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php add_track_button_to_submitted.php
 * Then delete this script. No migration needed.
 *
 * Backs up the file before overwriting (.bak, .bak2, ... if needed).
 */

function die_loud($msg) {
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup_file($path) {
    if (!file_exists($path)) {
        die_loud("Expected file not found: $path");
    }
    $backupPath = $path . '.bak';
    $n = 1;
    while (file_exists($backupPath)) {
        $n++;
        $backupPath = $path . '.bak' . $n;
    }
    if (!copy($path, $backupPath)) {
        die_loud("Could not create backup at $backupPath");
    }
    echo "Backed up " . $path . " -> " . basename($backupPath) . "\n";
}

function apply_patch($content, $old, $new, $label) {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        die_loud("Patch '$label' expected exactly 1 match but found $count.\nThe file may have drifted -- please re-paste it so the patch can be updated.");
    }
    return str_replace($old, $new, $content);
}

$root = __DIR__;
$viewPath = $root . '/resources/views/portal/submitted.blade.php';
$content = file_get_contents($viewPath);
if ($content === false) die_loud("Could not read $viewPath");

$old = <<<'OLD'
    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Please keep your transaction number for follow-up inquiries.
    </p>
OLD;

$new = <<<'NEW'
    <p style="font-size:.82rem;color:#555;text-align:center;">
      A confirmation email has been sent to <strong>{{ $candidate->email }}</strong>.<br>
      Please keep your transaction number for follow-up inquiries.
    </p>

    <div class="text-center mb-3 mt-1">
      <a href="{{ url('/') }}?txn={{ urlencode($transactionNumber) }}"
         style="display:inline-flex;align-items:center;gap:8px;background:#2b7a78;color:#fff;
                font-weight:700;font-size:.9rem;padding:10px 24px;border-radius:8px;
                text-decoration:none;">
        <i class="bi bi-search"></i> Track Your Application
      </a>
      <div style="font-size:.75rem;color:#888;margin-top:6px;">
        Uses your transaction number automatically
      </div>
    </div>
NEW;

$newContent = apply_patch($content, $old, $new, 'track-button-insert');

backup_file($viewPath);
if (file_put_contents($viewPath, $newContent) === false) die_loud("Could not write $viewPath");
echo "Updated resources/views/portal/submitted.blade.php\n";

echo "\nDone.\n";
echo "Next steps:\n";
echo "  1. Submit a test application via /portal/register.\n";
echo "  2. On the confirmation page, confirm 'Track Your Application' button appears\n";
echo "     below the email note.\n";
echo "  3. Click it -- confirm it goes to the tracking page with the transaction\n";
echo "     number pre-filled (check if your tracking page reads ?txn= from the URL).\n";
echo "  4. If the tracker doesn't auto-fill from ?txn= yet, paste the tracking\n";
echo "     page view file and I'll wire it.\n";
echo "  5. Delete this script once confirmed.\n";
