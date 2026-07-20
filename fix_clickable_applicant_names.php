<?php
/**
 * fix_clickable_applicant_names.php
 *
 * Applicant names appear in 3 places on the job posting pipeline page
 * (Qualification Checking, Open Ranking & Scheduling, Assessment &
 * Results) as plain text -- no way to view the applicant's full
 * information without leaving the pipeline and finding them again on
 * the standalone /applications list.
 *
 * This makes each one a link straight to that applicant's existing
 * detail page (applications.show), opening in a new tab so the
 * pipeline view underneath stays open.
 *
 * HOW TO RUN:
 *   php fix_clickable_applicant_names.php   (from project root)
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

echo "\n=== fix_clickable_applicant_names.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Qualification Checking panel (step 2) ───────────────────────────

echo "[1] Making applicant name clickable in Qualification Checking...\n";

apply_patch(
    $showPath,
    '                            <div>
                                <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                </div>
                            </div>',
    '                            <div>
                                <a href="{{ route(\'applications.show\', $app->id) }}" target="_blank" rel="noopener"
                                   class="fw-medium text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                   title="View applicant information" onclick="event.stopPropagation()">
                                    {{ $app->candidate->full_name }}
                                </a>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                </div>
                            </div>',
    'show.blade.php: Qualification Checking applicant name links to applications.show'
);

// ─── 2. Open Ranking & Scheduling table (step 3) ────────────────────────

echo "\n[2] Making applicant name clickable in Open Ranking & Scheduling...\n";

apply_patch(
    $showPath,
    '                                <td class="fw-medium">{{ $first->application->candidate->full_name }}</td>',
    '                                <td class="fw-medium">
                                    <a href="{{ route(\'applications.show\', $first->application_id) }}" target="_blank" rel="noopener"
                                       class="text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                       title="View applicant information">
                                        {{ $first->application->candidate->full_name }}
                                    </a>
                                </td>',
    'show.blade.php: Scheduling table applicant name links to applications.show'
);

// ─── 3. Assessment & Results table (step 4) ─────────────────────────────

echo "\n[3] Making applicant name clickable in Assessment & Results...\n";

apply_patch(
    $showPath,
    '                                <td class="fw-medium">{{ $cand->candidate_name }}</td>',
    '                                <td class="fw-medium">
                                    <a href="{{ route(\'applications.show\', $cand->application_id) }}" target="_blank" rel="noopener"
                                       class="text-decoration-none" style="color: inherit; border-bottom: 1px dashed #adb5bd;"
                                       title="View applicant information">
                                        {{ $cand->candidate_name }}
                                    </a>
                                </td>',
    'show.blade.php: Assessment & Results applicant name links to applications.show'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Applicant names in all 3 pipeline panels (Qualification\n";
echo "    Checking, Open Ranking & Scheduling, Assessment & Results) are\n";
echo "    now clickable links to that applicant's existing detail page\n";
echo "    (/applications/{id}), opening in a new tab.\n";
echo "  - Qualification Checking's name link stops the click from also\n";
echo "    triggering the qualification-check button/card behavior next to\n";
echo "    it (event.stopPropagation()).\n\n";
echo "DELETE this script after running.\n";
