<?php
/**
 * Patch: two changes to the Records page.
 *
 * 1. Pending table's "Assign Code" input is pre-filled with
 *    "SDO-{current year}-" instead of starting blank -- staff just
 *    types the numeric suffix. Year comes from now()->format('Y')
 *    rather than a hardcoded "2026", so this stays correct after the
 *    year rolls over without needing another patch (matches how
 *    Application::generateTransactionNumber() builds its own prefix).
 *
 * 2. "Assigned Application Codes" table's editable input + Save button
 *    is removed -- the code now displays as plain text, no longer
 *    editable from this page. NOTE: this only removes the UI.
 *    RecordsController::updateCode() and its route are left in place
 *    untouched -- if you also want the backend route removed/blocked,
 *    say so and I'll patch that separately (didn't want to guess on a
 *    route removal without confirming you're not using it elsewhere,
 *    e.g. a direct link from an audit page).
 *
 * Run once from the project root:
 *   php patch_records_prefill_and_remove_edit.php
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
$file = __DIR__ . '/resources/views/records/index.blade.php';

apply_patch($file, [
    // 1. Pre-fill the Assign Code input with the current year's prefix.
    [
        <<<'OLD'
                    <input type="text" name="transaction_number"
                           class="form-control form-control-sm" style="max-width: 200px;"
                           placeholder="Auto-generate (or type a code)">
OLD,
        <<<'NEW'
                    <input type="text" name="transaction_number"
                           class="form-control form-control-sm" style="max-width: 200px;"
                           value="SDO-{{ now()->format('Y') }}-"
                           placeholder="Auto-generate (or type a code)">
NEW,
        'pre-fill Assign Code input with SDO-{year}- prefix',
    ],

    // 2. Remove the editable input + Save button in the Assigned table;
    //    show the code as plain text instead.
    [
        <<<'OLD'
            <td>
                <form action="{{ route('records.update-code', $application->id) }}" method="POST" class="d-flex gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="transaction_number"
                           value="{{ $application->transaction_number }}"
                           class="form-control form-control-sm" required>
                    <button type="submit" class="btn btn-sm btn-outline-primary"
                        onclick="return confirm('Update this Application Code? This will NOT resend the assignment email.');">
                        Save
                    </button>
                </form>
                @error('transaction_number')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </td>
OLD,
        <<<'NEW'
            <td>
                <span class="font-monospace">{{ $application->transaction_number }}</span>
            </td>
NEW,
        'Assigned table: replace editable code input with plain text',
    ],
]);

echo "\nDone. Diff and check the Records page before deleting this script\n";
echo "and its .bak backup.\n";
