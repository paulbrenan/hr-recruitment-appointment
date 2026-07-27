<?php
/**
 * fix_advance_redirect_and_pending_order.php
 *
 * [1] "Move to Scheduling" not redirecting to Scheduling
 *
 *     Root cause: saveQualificationCheck() redirects HR back with
 *     ?step=2 in the URL so they land back on Qualification Checking
 *     after saving a check. That query string then STAYS in the browser
 *     address bar. If HR clicks "Move to Scheduling" while that ?step=2
 *     is still there, advanceStep()'s success handler does a bare
 *     window.location.reload() -- which reloads the SAME URL, ?step=2
 *     and all. show() then computes:
 *         activeStep = min(requestedStep=2, currentStep=3) = 2
 *     ...landing back on Qualification Checking even though the posting
 *     DID actually advance to Interview Scheduling on the backend. The
 *     status change is real; only the panel shown after reload is wrong.
 *
 *     Fix: navigate to the clean show URL (no query string) instead of
 *     reload()ing whatever's currently in the address bar.
 *
 * [2] Qualification Checking group order
 *
 *     Groups render Qualified -> Disqualified -> Pending. The one HR
 *     actually needs to act on (Pending) was buried at the bottom.
 *     Reordered to Pending -> Qualified -> Disqualified so it's the
 *     first thing HR sees.
 *
 * HOW TO RUN:
 *   php fix_advance_redirect_and_pending_order.php   (from project root)
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

echo "\n=== fix_advance_redirect_and_pending_order.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. advanceStep(): navigate to clean URL instead of reload() ────────

echo "[1] Patching advanceStep() to drop stale ?step= before reloading...\n";

apply_patch(
    $showPath,
    "    fetch('{{ route('job-postings.advance', \$posting->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(() => window.location.reload())
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Advance';
        alert('Failed to advance. Please try again.');
    });",
    "    fetch('{{ route('job-postings.advance', \$posting->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(() => {
        // Navigate to the CLEAN show URL, not reload() -- reload() reuses
        // whatever query string is currently in the address bar (e.g. a
        // leftover ?step=2 from an earlier \"save qualification check\"
        // redirect), which would clamp activeStep back down to a step
        // BEFORE the one this posting just advanced to.
        window.location.href = '{{ route('job-postings.show', \$posting->id) }}';
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Advance';
        alert('Failed to advance. Please try again.');
    });",
    'show.blade.php: advanceStep() navigates to clean URL after advancing'
);

// ─── 2. Qualification Checking: Pending group shown first ──────────────

echo "\n[2] Reordering qualification groups so Pending shows first...\n";

apply_patch(
    $showPath,
    "                        \$qualGroups = [
                            'qualified'     => \$applications->where('qualification_result', 'qualified')->values(),
                            'not_qualified' => \$applications->where('qualification_result', 'not_qualified')->values(),
                            'pending'       => \$applications->whereNull('qualification_result')->values(),
                        ];
                        \$qualGroupMeta = [
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                            'pending'       => ['label' => 'Pending qualification check', 'color' => 'secondary'],
                        ];",
    "                        // Pending shown FIRST -- it's the group HR actually needs\n" .
    "                        // to act on; Qualified/Disqualified are already resolved.\n" .
    "                        \$qualGroups = [\n" .
    "                            'pending'       => \$applications->whereNull('qualification_result')->values(),\n" .
    "                            'qualified'     => \$applications->where('qualification_result', 'qualified')->values(),\n" .
    "                            'not_qualified' => \$applications->where('qualification_result', 'not_qualified')->values(),\n" .
    "                        ];\n" .
    "                        \$qualGroupMeta = [\n" .
    "                            'pending'       => ['label' => 'Pending qualification check', 'color' => 'secondary'],\n" .
    "                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],\n" .
    "                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],\n" .
    "                        ];",
    'show.blade.php: Pending group rendered before Qualified/Disqualified'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Clicking \"Move to Scheduling\" (or any advance button) now\n";
echo "    reliably lands on the newly-unlocked panel, even if the address\n";
echo "    bar still had a stale ?step= from an earlier action.\n";
echo "  - Qualification Checking panel now lists Pending applicants first,\n";
echo "    then Qualified, then Disqualified.\n\n";
echo "DELETE this script after running.\n";
