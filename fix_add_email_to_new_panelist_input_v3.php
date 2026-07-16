<?php
/**
 * fix_add_email_to_new_panelist_input_v3.php
 *
 * Same as v2, minus the "Tick Available" hint-text cleanup step -- that
 * text was already clean in the live file (fixed separately already).
 * Only the email-input-in-new-panelist-row and syncPanelists() changes
 * remain to apply.
 *
 * HOW TO RUN:
 *   php fix_add_email_to_new_panelist_input_v3.php   (from project root)
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

echo "\n=== fix_add_email_to_new_panelist_input_v3.php ===\n\n";

$formPath       = ROOT . '/resources/views/job-postings/form.blade.php';
$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// ─── 1. Add email input next to the name input ──────────────────────────

echo "[1] Adding email input to the inline new-panelist row...\n";

$oldRow = <<<'EOT'
            <input type="text" class="form-control" name="new_panelist_names[]" placeholder="New panelist name..." autocomplete="off">
EOT;

$newRow = <<<'EOT'
            <input type="text" class="form-control" name="new_panelist_names[]" placeholder="New panelist name..." autocomplete="off" style="flex:1 1 45%;">
            <input type="email" class="form-control" name="new_panelist_emails[]" placeholder="Email (optional)" autocomplete="off" style="flex:1 1 45%;">
EOT;

apply_patch($formPath, $oldRow, $newRow, 'form.blade.php: email input added to new-panelist row');

// ─── 2. syncPanelists() saves the email too ─────────────────────────────

echo "\n[2] Patching syncPanelists() to save new_panelist_emails[]...\n";

apply_patch(
    $controllerPath,
    "        // Create any newly added panelists
        \$newNames = array_filter(array_map('trim', \$request->input('new_panelist_names', [])));
        foreach (\$newNames as \$name) {
            if (\$name !== '') {
                \$new = Panelist::create(['name' => \$name]);
                // Add to assigned list so they get synced below
                \$request->merge([
                    'panelist_ids' => array_merge(\$request->input('panelist_ids', []), [\$new->id]),
                ]);
            }
        }",
    "        // Create any newly added panelists (name is required, email is
        // optional but needed for that panelist to actually receive
        // schedule invitation emails -- see InterviewScheduleController).
        \$newNames  = \$request->input('new_panelist_names', []);
        \$newEmails = \$request->input('new_panelist_emails', []);
        foreach (\$newNames as \$i => \$name) {
            \$name = trim(\$name);
            if (\$name === '') {
                continue;
            }
            \$email = trim(\$newEmails[\$i] ?? '');
            \$new = Panelist::create([
                'name'  => \$name,
                'email' => \$email !== '' ? \$email : null,
            ]);
            // Add to assigned list so they get synced below
            \$request->merge([
                'panelist_ids' => array_merge(\$request->input('panelist_ids', []), [\$new->id]),
            ]);
        }",
    'JobPostingController::syncPanelists() -- captures email for new panelists'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Adding a new panelist inline on the job posting form now has an\n";
echo "    optional email field alongside the name field.\n";
echo "  - Panelists created this way with an email will actually receive\n";
echo "    schedule invitation emails (testable via Mailpit).\n\n";
echo "DELETE this script after running.\n";
