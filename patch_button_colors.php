<?php
/**
 * Patch: make the export buttons match "View / Print CAR"'s solid green
 * style, and the email-send buttons solid blue, instead of outline.
 *
 *   - Export qualifications: btn-outline-success -> btn-success
 *   - Export IER:            btn-outline-success -> btn-success
 *   - Send all emails (schedule notices): btn-outline-primary -> btn-primary
 *   - Send all notifications (ranking emails): btn-outline-primary -> btn-primary
 *
 * Run once from the project root:
 *   php patch_button_colors.php
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
$showBladeFile = __DIR__ . '/resources/views/job-postings/show.blade.php';

apply_patch($showBladeFile, [
    [
        "                               class=\"btn btn-sm btn-outline-success\">",
        "                               class=\"btn btn-sm btn-success\">",
        'Export qualifications: solid green',
    ],
    [
        '<a href="{{ route(\'job-postings.export-ier\', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-outline-success ms-2">',
        '<a href="{{ route(\'job-postings.export-ier\', $posting->id) }}" id="export-ier-btn" data-no-loader class="btn btn-sm btn-success ms-2">',
        'Export IER: solid green',
    ],
    [
        <<<'OLD'
                                <button type="submit" class="btn btn-sm btn-outline-primary"
                                        onclick="return confirm('Send emails to {{ $pendingScheduleNotices }} applicant(s)? Qualified applicants with a schedule get the qualified letter + schedule; everyone else gets a disqualification notice.')">
OLD,
        <<<'NEW'
                                <button type="submit" class="btn btn-sm btn-primary"
                                        onclick="return confirm('Send emails to {{ $pendingScheduleNotices }} applicant(s)? Qualified applicants with a schedule get the qualified letter + schedule; everyone else gets a disqualification notice.')">
NEW,
        'Send all emails (schedule notices): solid blue',
    ],
    [
        <<<'OLD'
                    <button type="submit" class="btn btn-sm btn-outline-primary"
                            onclick="return confirm('Send ranking notifications to all {{ $rankedCandidates->count() }} applicant(s)?')">
OLD,
        <<<'NEW'
                    <button type="submit" class="btn btn-sm btn-primary"
                            onclick="return confirm('Send ranking notifications to all {{ $rankedCandidates->count() }} applicant(s)?')">
NEW,
        'Send all notifications (ranking emails): solid blue',
    ],
]);

echo "\nDone. Diff and reload the posting show page before deleting this script and its .bak backup.\n";
