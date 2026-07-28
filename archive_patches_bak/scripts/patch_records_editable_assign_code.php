<?php
/**
 * Patch: let Records optionally type/edit the Application Code before
 * assigning it, instead of only ever getting whatever
 * Application::generateTransactionNumber() produces.
 *
 * Design: the input starts BLANK (not pre-filled with a suggested next
 * code) -- pre-filling per pending row would mean computing a "next
 * code" preview for every row on every page load, which isn't reserved
 * and could go stale/collide by the time staff actually clicks Assign
 * across multiple rows. Leaving it blank keeps today's default
 * behavior (auto-generate) as the zero-effort path, while giving staff
 * the option to type a specific code when they need to override one.
 *
 * Uniqueness is validated server-side exactly like the existing
 * updateCode() method already does for corrections.
 *
 * Run once from the project root:
 *   php patch_records_editable_assign_code.php
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

// ── Adjust these paths if your project layout differs ──────────────────
$controllerFile = __DIR__ . '/app/Http/Controllers/RecordsController.php';
$viewFile = __DIR__ . '/resources/views/records/index.blade.php';

apply_patch($controllerFile, [
    [
        <<<'OLD'
    public function assignCode($id)
    {
        $application = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->findOrFail($id);

        DB::transaction(function () use ($application) {
            $application->update([
                'transaction_number' => Application::generateTransactionNumber(),
            ]);
        });
OLD,
        <<<'NEW'
    public function assignCode(Request $request, $id)
    {
        $application = Application::with(['candidate', 'jobPosting'])
            ->whereNull('transaction_number')
            ->findOrFail($id);

        // Optional manual override -- if Records typed a specific code,
        // validate and use that instead of auto-generating. Left blank
        // (the default), behavior is unchanged from before.
        $validated = $request->validate([
            'transaction_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:applications,transaction_number',
            ],
        ]);

        $manualCode = trim((string) ($validated['transaction_number'] ?? ''));

        DB::transaction(function () use ($application, $manualCode) {
            $application->update([
                'transaction_number' => $manualCode !== ''
                    ? $manualCode
                    : Application::generateTransactionNumber(),
            ]);
        });
NEW,
        'assignCode(): accept optional manual transaction_number override',
    ],
]);

apply_patch($viewFile, [
    [
        <<<'OLD'
            <td class="text-end">
                <form action="{{ route('records.assign-code', $application->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary"
                        onclick="return confirm('Confirm requirements have been checked. This will assign the Application Code and email it to the applicant.');">
                        Assign Code
                    </button>
                </form>
            </td>
OLD,
        <<<'NEW'
            <td class="text-end">
                <form action="{{ route('records.assign-code', $application->id) }}" method="POST" class="d-flex gap-2 justify-content-end">
                    @csrf
                    <input type="text" name="transaction_number"
                           class="form-control form-control-sm" style="max-width: 200px;"
                           placeholder="Auto-generate (or type a code)">
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap"
                        onclick="return confirm('Confirm requirements have been checked. This will assign the Application Code and email it to the applicant.');">
                        Assign Code
                    </button>
                </form>
                @error('transaction_number')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </td>
NEW,
        'pending table: editable code input before Assign Code',
    ],
]);

echo "\nDone. Diff and test assigning a code both blank (auto-generate,\n";
echo "unchanged behavior) and with a manual value before deleting this\n";
echo "script and its .bak backups.\n";
