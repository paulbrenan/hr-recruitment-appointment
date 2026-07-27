<?php
/**
 * patch_criteria_scan_fix2.php
 *
 * Fixes two more issues found while debugging the criteria scan:
 *
 *   1. resources/views/job-postings/show.blade.php only ever displays
 *      session('success'). There's no block for session('error') or
 *      validation errors anywhere on that page, so whenever
 *      importCriteriaScan() fails for a legitimate reason (unreadable
 *      file, no recognized criteria names, etc.) the message is
 *      silently discarded and the page just reloads with nothing
 *      visibly different -- which is why the scan has looked like it
 *      "does nothing" even when it's actually failing predictably.
 *
 *   2. AssessmentController's shell_exec() calls redirect stderr with
 *      `2>/dev/null`, but /dev/null doesn't exist on Windows. On a
 *      Windows/XAMPP setup that redirect target is invalid and can
 *      make the whole pdftotext/pdftoppm/tesseract command misbehave.
 *      This adds an OS-aware nullDevice() helper (NUL on Windows,
 *      /dev/null elsewhere) and uses it in all four shell_exec calls.
 *
 * Run from your Laravel project root:
 *   php patch_criteria_scan_fix2.php
 *
 * Creates .bak backups of both files before editing. Aborts with no
 * changes to either file if any expected original code isn't found.
 */

$controllerPath = __DIR__ . '/app/Http/Controllers/AssessmentController.php';
$viewPath       = __DIR__ . '/resources/views/job-postings/show.blade.php';

foreach ([$controllerPath, $viewPath] as $p) {
    if (!file_exists($p)) {
        fwrite(STDERR, "ABORT: Could not find {$p}\n");
        fwrite(STDERR, "Run this script from your Laravel project root.\n");
        exit(1);
    }
}

$controllerSrc = file_get_contents($controllerPath);
$viewSrc       = file_get_contents($viewPath);

// ── Guards + patch: AssessmentController.php ───────────────────────────────
$replacements = [
    // Insert the nullDevice() helper right before extractTextFromPdf().
    [
        'old' => "    private function extractTextFromPdf(string \$path): string\n    {",
        'new' => "    /**\n     * Windows' shell has no /dev/null -- shell_exec() there runs through\n     * cmd.exe, which doesn't understand that path and can make the whole\n     * redirected command misbehave. NUL is the Windows equivalent.\n     */\n    private function nullDevice(): string\n    {\n        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';\n    }\n\n    private function extractTextFromPdf(string \$path): string\n    {",
    ],
    [
        'old' => "        \$layoutText = @shell_exec('pdftotext -layout ' . escapeshellarg(\$path) . ' - 2>/dev/null');",
        'new' => "        \$layoutText = @shell_exec('pdftotext -layout ' . escapeshellarg(\$path) . ' - 2>' . \$this->nullDevice());",
    ],
    [
        'old' => "        \$plainText  = @shell_exec('pdftotext ' . escapeshellarg(\$path) . ' - 2>/dev/null');",
        'new' => "        \$plainText  = @shell_exec('pdftotext ' . escapeshellarg(\$path) . ' - 2>' . \$this->nullDevice());",
    ],
    [
        'old' => "        shell_exec('pdftoppm -png -r 200 ' . escapeshellarg(\$path) . ' ' . escapeshellarg(\$prefix) . ' 2>/dev/null');",
        'new' => "        shell_exec('pdftoppm -png -r 200 ' . escapeshellarg(\$path) . ' ' . escapeshellarg(\$prefix) . ' 2>' . \$this->nullDevice());",
    ],
    [
        'old' => "        return (string) @shell_exec('tesseract ' . escapeshellarg(\$path) . ' stdout 2>/dev/null');",
        'new' => "        return (string) @shell_exec('tesseract ' . escapeshellarg(\$path) . ' stdout 2>' . \$this->nullDevice());",
    ],
];

foreach ($replacements as $i => $r) {
    if (!str_contains($controllerSrc, $r['old'])) {
        fwrite(STDERR, "ABORT: AssessmentController.php replacement #{$i} didn't match the expected code.\n");
        fwrite(STDERR, "No changes made to either file. The controller may not have patch_criteria_scan_fix.php\n");
        fwrite(STDERR, "applied yet, or has changed since. Run that patch first if you haven't.\n");
        exit(1);
    }
}

// ── Guard + patch: show.blade.php ──────────────────────────────────────────
$oldView = <<<'BLADE'
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show small py-2">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
BLADE;

$newView = <<<'BLADE'
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show small py-2">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show small py-2">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show small py-2">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
BLADE;

if (!str_contains($viewSrc, $oldView)) {
    fwrite(STDERR, "ABORT: show.blade.php's success-alert block didn't match the expected code.\n");
    fwrite(STDERR, "No changes made to either file.\n");
    exit(1);
}

// ── Apply (both files, since we've verified both guards pass) ─────────────
$controllerBackup = $controllerPath . '.bak2';
$viewBackup        = $viewPath . '.bak2';

if (!copy($controllerPath, $controllerBackup)) {
    fwrite(STDERR, "ABORT: Could not create backup at {$controllerBackup}\n");
    exit(1);
}
if (!copy($viewPath, $viewBackup)) {
    fwrite(STDERR, "ABORT: Could not create backup at {$viewBackup}\n");
    exit(1);
}

$patchedController = $controllerSrc;
foreach ($replacements as $r) {
    $patchedController = str_replace($r['old'], $r['new'], $patchedController);
}
file_put_contents($controllerPath, $patchedController);

$patchedView = str_replace($oldView, $newView, $viewSrc);
file_put_contents($viewPath, $patchedView);

echo "Patched: {$controllerPath}\n";
echo "Backup:  {$controllerBackup}\n";
echo "Patched: {$viewPath}\n";
echo "Backup:  {$viewBackup}\n";
echo "\nWhat changed:\n";
echo "  1. AssessmentController's shell_exec() calls now use NUL instead of\n";
echo "     /dev/null on Windows, so pdftotext/pdftoppm/tesseract aren't\n";
echo "     handed an invalid redirect target.\n";
echo "  2. The job posting pipeline page now shows session('error') and\n";
echo "     validation error banners, not just session('success') -- so if\n";
echo "     the scan fails for any reason, you'll actually see why.\n";
echo "\nTry scanning the CAR PDF again. If it still doesn't add criteria,\n";
echo "you should now see a red banner explaining why -- send me that message.\n";
