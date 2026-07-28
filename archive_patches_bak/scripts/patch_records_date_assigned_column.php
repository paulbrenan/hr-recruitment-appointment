<?php
/**
 * Patch: replace the empty "Action" column in the Assigned Application
 * Codes table with a "Date assigned" column.
 *
 * ASSUMPTION (please verify): there's no dedicated "code assigned at"
 * timestamp on the applications table that I could see, so this uses
 * $application->updated_at. That's accurate for records assigned via
 * assignCode() and never touched again, but note updateCode() (fixing a
 * mistyped code) also bumps updated_at -- so a corrected code will show
 * the correction date, not the original assignment date. If you have (or
 * want) a dedicated code_assigned_at column instead, let me know and
 * I'll redo this against that.
 *
 * Run once from the project root:
 *   php patch_records_date_assigned_column.php
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
$indexBladeFile = __DIR__ . '/resources/views/records/index.blade.php';

apply_patch($indexBladeFile, [
    [
        <<<'OLD'
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th style="min-width: 220px;">Application Code</th>
            <th class="text-end">Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($assigned as $application)
        <tr>
            <td>{{ $application->candidate->full_name ?? '—' }}</td>
            <td>{{ $application->jobPosting->title ?? '—' }}</td>
            <td>
                <span class="font-monospace">{{ $application->transaction_number }}</span>
            </td>
            <td class="text-end"></td>
        </tr>
OLD,
        <<<'NEW'
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th style="min-width: 220px;">Application Code</th>
            <th class="text-end">Date assigned</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($assigned as $application)
        <tr>
            <td>{{ $application->candidate->full_name ?? '—' }}</td>
            <td>{{ $application->jobPosting->title ?? '—' }}</td>
            <td>
                <span class="font-monospace">{{ $application->transaction_number }}</span>
            </td>
            <td class="text-end">{{ $application->updated_at?->format('M d, Y') ?? '—' }}</td>
        </tr>
NEW,
        'Assigned table: Action column -> Date assigned',
    ],
]);

echo "\nDone. Diff and reload the Records page before deleting this script and its .bak backup.\n";
