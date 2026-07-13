<?php
/**
 * add_txn_autofill_to_landing.php
 *
 * One-shot script: patches resources/views/welcome.blade.php (the public
 * landing/tracking page) to auto-fill the transaction number input and
 * automatically trigger the track lookup when the page loads with a
 * ?txn= query parameter -- so the "Track Your Application" button on
 * the submitted confirmation page lands the applicant directly on their
 * result without needing to re-type their transaction number.
 *
 * Usage: place this file in the project root (same folder as artisan) and run:
 *   php add_txn_autofill_to_landing.php
 * Then delete this script. No migration needed.
 *
 * Backs up the file before overwriting (.bak, .bak2, ... if needed).
 *
 * NOTE: if the landing page lives at a different path than
 * resources/views/welcome.blade.php, update $viewPath below.
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

// ── Locate the landing page view ─────────────────────────────────────────────
// Try the most likely paths; update this if your file lives elsewhere.
$candidates = [
    $root . '/resources/views/welcome.blade.php',
    $root . '/resources/views/landing.blade.php',
    $root . '/resources/views/portal/landing.blade.php',
];

$viewPath = null;
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $viewPath = $candidate;
        break;
    }
}

if ($viewPath === null) {
    die_loud(
        "Could not find the landing page view. Tried:\n" .
        implode("\n", $candidates) . "\n\n" .
        "Please update \$candidates in this script with the correct path."
    );
}

echo "Found landing page at: $viewPath\n";

$content = file_get_contents($viewPath);
if ($content === false) die_loud("Could not read $viewPath");

// ── Verify this is the right file ────────────────────────────────────────────
if (strpos($content, 'trackApplication') === false || strpos($content, 'txnInput') === false) {
    die_loud(
        "The file at $viewPath doesn't look like the tracking landing page\n" .
        "(missing 'trackApplication' or 'txnInput').\n" .
        "Please paste the correct path in this script."
    );
}

if (strpos($content, 'autoFillFromUrl') !== false) {
    die_loud("Auto-fill code already exists in $viewPath -- looks like this script already ran.");
}

// ── Patch: append auto-fill logic before the closing </script> ────────────────
$old = <<<'OLD'
// ESC to close modal
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});
</script>
OLD;

$new = <<<'NEW'
// ESC to close modal
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeModalDirect();
});

// ── Auto-fill transaction number from ?txn= query parameter ──────────────────
// Triggered when the user arrives via the "Track Your Application" button
// on the submitted confirmation page, which links to /?txn=APP-YYYYMMDD-XXXXXX.
(function autoFillFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const txn = params.get('txn');
  if (!txn) return;

  const input = document.getElementById('txnInput');
  if (!input) return;

  input.value = txn.trim().toUpperCase();

  // Auto-trigger the lookup after a short delay so the page has rendered
  // and the modal animation feels intentional rather than instant.
  setTimeout(trackApplication, 400);
})();
</script>
NEW;

$newContent = apply_patch($content, $old, $new, 'autofill-from-url');

backup_file($viewPath);
if (file_put_contents($viewPath, $newContent) === false) die_loud("Could not write $viewPath");
echo "Updated $viewPath\n";

echo "\nDone. No migration needed.\n";
echo "Next steps:\n";
echo "  1. Submit a test application via /portal/register.\n";
echo "  2. On the confirmation page, click 'Track Your Application'.\n";
echo "  3. Confirm the landing page opens with the transaction number pre-filled\n";
echo "     in the input and the result modal appearing automatically.\n";
echo "  4. Delete this script once confirmed.\n";
