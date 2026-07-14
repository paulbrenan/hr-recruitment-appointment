<?php

/**
 * patch_qualification_groups.php
 *
 * WHAT THIS DOES:
 *   Splits the flat "Qualification checking" list (Step 2 panel on the job
 *   posting show page) into three grouped sections:
 *
 *     - Disqualified   (qualification_result === 'not_qualified')
 *     - Qualified      (qualification_result === 'qualified')
 *     - Pending qualification check (qualification_result is null)
 *
 *   This is DISPLAY ONLY -- the qualify/disqualify decision itself was
 *   already automatic (ApplicationController::saveQualificationCheck()
 *   already sets qualification_result to 'qualified' only if every
 *   criterion passes). This patch just groups by that stored result
 *   instead of dumping every applicant into one undifferentiated list.
 *
 *   Grouping uses qualification_result rather than the applicant's current
 *   pipeline `status`, so an applicant who qualified and then moved on to
 *   interview_scheduled / ranked / hired still correctly stays filed under
 *   "Qualified" (their status badge on the card still reflects wherever
 *   they currently are in the pipeline).
 *
 *   No changes to routes, controllers, or the qualification-check modal --
 *   the per-applicant card markup, buttons, and JS hooks are untouched,
 *   just re-wrapped into three @foreach groups instead of one @forelse.
 *
 * HOW TO RUN:
 *   php patch_qualification_groups.php    (from project root)
 *   No migration needed.
 *
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
        echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n";
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

echo "\n=== patch_qualification_groups.php ===\n\n";

$bladePath = ROOT . '/resources/views/job-postings/show.blade.php';

$old = <<<'EOT'
        {{-- ══ STEP 2 — Qualification Checking ════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                    </div>

                    @forelse ($applications as $app)
                    @php
                        $qColors = ['qualified'=>'success','not_qualified'=>'danger','hired'=>'dark','ranking_sent'=>'primary','interview_scheduled'=>'info','submitted'=>'secondary','rejected'=>'secondary'];
                        $qColor = $qColors[$app->status] ?? 'secondary';
                        $appCheck = $app->qualification_check ?? [];
                        // Candidate's self-reported qualifications — used only
                        // as the modal's starting point when no qualification
                        // check has been saved yet for that criterion; a saved
                        // "actual" value always takes precedence (see the
                        // qualCheckModal JS below). Same fields/precedence as
                        // the standalone /applications/{id} page.
                        $appSelfReported = [
                            'education'   => $app->candidate->education ?? null,
                            'experience'  => $app->candidate->years_experience ?? null,
                            'training'    => $app->candidate->training_hours ?? null,
                            'eligibility' => $app->candidate->eligibility ?? null,
                        ];
                    @endphp
                    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;">
                        <div>
                            <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                            <div class="text-muted small">
                                Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge text-bg-{{ $qColor }}">
                                {{ str_replace('_', ' ', ucfirst($app->status)) }}
                            </span>
                            @if (!in_array($app->status, ['hired', 'ranking_sent']))
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                    data-application-id="{{ $app->id }}"
                                    data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                    data-check="{{ json_encode($appCheck) }}"
                                    data-self-reported="{{ json_encode($appSelfReported) }}">
                                <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                            </button>
                            @endif
                            @if ($app->qualification_result)
                            <form action="{{ route('applications.qualification-notice', $app->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $app->qualification_notified_at ? 'Resend result' : 'Email result' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse
                </div>
            </div>
        </div>
EOT;

$new = <<<'EOT'
        {{-- ══ STEP 2 — Qualification Checking ════════════════════════════ --}}
        <div class="step-panel d-none" id="panel-2">
            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                    </div>

                    @if ($applications->isEmpty())
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @else
                    @php
                        // Group by the PERSISTED qualification_result, not by
                        // status -- status keeps advancing (interview_scheduled,
                        // ranked, hired, etc.) once an applicant qualifies, but
                        // qualification_result stays 'qualified' until HR re-runs
                        // the check, so this is what keeps the grouping stable.
                        // Applicants who haven't been checked yet have a null
                        // qualification_result and land in "Pending".
                        $qualGroups = [
                            'not_qualified' => $applications->where('qualification_result', 'not_qualified')->values(),
                            'qualified'     => $applications->where('qualification_result', 'qualified')->values(),
                            'pending'       => $applications->whereNull('qualification_result')->values(),
                        ];
                        $qualGroupMeta = [
                            'not_qualified' => ['label' => 'Disqualified', 'color' => 'danger'],
                            'qualified'     => ['label' => 'Qualified', 'color' => 'success'],
                            'pending'       => ['label' => 'Pending qualification check', 'color' => 'secondary'],
                        ];
                    @endphp
                    @foreach ($qualGroups as $groupKey => $groupApps)
                    <div class="mb-4">
                        <h6 class="text-uppercase small fw-bold text-{{ $qualGroupMeta[$groupKey]['color'] }} mb-2" style="letter-spacing:.03em;">
                            {{ $qualGroupMeta[$groupKey]['label'] }}
                            <span class="badge text-bg-light text-dark border ms-1">{{ $groupApps->count() }}</span>
                        </h6>
                        @forelse ($groupApps as $app)
                        @php
                            $qColors = ['qualified'=>'success','not_qualified'=>'danger','hired'=>'dark','ranking_sent'=>'primary','interview_scheduled'=>'info','submitted'=>'secondary','rejected'=>'secondary'];
                            $qColor = $qColors[$app->status] ?? 'secondary';
                            $appCheck = $app->qualification_check ?? [];
                            // Candidate's self-reported qualifications — used only
                            // as the modal's starting point when no qualification
                            // check has been saved yet for that criterion; a saved
                            // "actual" value always takes precedence (see the
                            // qualCheckModal JS below). Same fields/precedence as
                            // the standalone /applications/{id} page.
                            $appSelfReported = [
                                'education'   => $app->candidate->education ?? null,
                                'experience'  => $app->candidate->years_experience ?? null,
                                'training'    => $app->candidate->training_hours ?? null,
                                'eligibility' => $app->candidate->eligibility ?? null,
                            ];
                        @endphp
                        <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;">
                            <div>
                                <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                                <div class="text-muted small">
                                    Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge text-bg-{{ $qColor }}">
                                    {{ str_replace('_', ' ', ucfirst($app->status)) }}
                                </span>
                                @if (!in_array($app->status, ['hired', 'ranking_sent']))
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#qualCheckModal"
                                        data-application-id="{{ $app->id }}"
                                        data-candidate-name="{{ addslashes($app->candidate->full_name) }}"
                                        data-check="{{ json_encode($appCheck) }}"
                                        data-self-reported="{{ json_encode($appSelfReported) }}">
                                    <i class="bi bi-clipboard-check me-1"></i> Check qualifications
                                </button>
                                @endif
                                @if ($app->qualification_result)
                                <form action="{{ route('applications.qualification-notice', $app->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        {{ $app->qualification_notified_at ? 'Resend result' : 'Email result' }}
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-muted small mb-0 py-2">None in this group.</p>
                        @endforelse
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>
EOT;

apply_patch($bladePath, $old, $new, 'panel-2: split flat applicant list into Disqualified / Qualified / Pending groups');

echo <<<TEXT

✅ Done. Hard-refresh the page (Ctrl+Shift+R).

HOW IT WORKS:
  - Applicants are grouped by their stored qualification_result column, in
    this order: Disqualified, Qualified, Pending qualification check.
  - "Pending" = qualification_result is still null (Check qualifications
    hasn't been run yet, or the last run left it null).
  - The qualify/disqualify decision itself is unchanged and was already
    automatic: saveQualificationCheck() in ApplicationController marks an
    applicant 'qualified' only if every criterion (education, experience,
    training, eligibility) is marked Qualified, otherwise 'not_qualified'.
  - Each applicant's card, buttons, and the qualification-check modal work
    exactly as before -- this patch only changes how the list is grouped
    for display.

DELETE this script after running.

TEXT;
