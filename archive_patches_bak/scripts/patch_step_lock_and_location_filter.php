<?php
/**
 * patch_step_lock_and_location_filter.php
 *
 * 1. STEP LOCK ("can't go back," per your clarification: viewing a passed
 *    step stays allowed, but its edit-triggering actions get disabled).
 *    Rule applied: an action is available only while you're still AT OR
 *    BEFORE the step that owns it; once the posting has moved past that
 *    step, the action is hidden. Gated actions:
 *      - Edit posting (sidebar AND panel-1 header) — hidden once
 *        $currentStep >= 3 (status moved past "open")
 *      - Remove panelist — same gate
 *      - Check qualifications (Step 2) — hidden once $currentStep >= 3
 *      - New schedule / Delete schedule (Step 3) — hidden once
 *        $currentStep >= 4
 *      - Add criterion / Remove criterion / Edit scores (Step 4) — hidden
 *        once the posting status is "closed" ($currentStep alone can't
 *        distinguish ranking vs. closed, both are step 4)
 *    NOTE: no export/import buttons exist in this file yet -- those come
 *    with the assessment code you're sending, and I'll make sure they're
 *    excluded from this gating when I wire them in, per your instruction
 *    that those stay functional regardless of step lock.
 *
 * 2. QUALIFICATION CHECKING LOCATION FILTER: when a posting has more than
 *    one place of assignment, a dropdown now appears above the applicant
 *    list letting HR filter to just one place at a time (client-side, no
 *    reload) -- uses Application::job_posting_location_id, which already
 *    exists on the model.
 *
 * HOW TO RUN:
 *   php patch_step_lock_and_location_filter.php   (project root)
 * DELETE this script after running.
 *
 * STILL PENDING: per-job scheduling restructure and the assessment
 * export/import feature copy -- waiting on the assessment code.
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

echo "\n=== patch_step_lock_and_location_filter.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Sidebar Edit posting button ───────────────────────────────────────

apply_patch(
    $showPath,
    '                <div class="mt-3 pt-3 border-top">
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                </div>',
    '                <div class="mt-3 pt-3 border-top">
                    @if ($currentStep < 3)
                    <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-pencil me-1"></i> Edit posting
                    </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" disabled
                            title="This posting can no longer be edited once scheduling has started.">
                        <i class="bi bi-lock me-1"></i> Edit posting
                    </button>
                    @endif
                </div>',
    'Sidebar: lock Edit posting once past step 2'
);

// ─── 2. Panel-1 header Edit posting button ────────────────────────────────

apply_patch(
    $showPath,
    '                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-{{ $statusColors[$posting->status] ?? \'secondary\' }}">
                                {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                            </span>
                            <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i> Edit posting
                            </a>
                        </div>',
    '                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-{{ $statusColors[$posting->status] ?? \'secondary\' }}">
                                {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                            </span>
                            @if ($currentStep < 3)
                            <a href="{{ route(\'job-postings.edit\', $posting->id) }}"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i> Edit posting
                            </a>
                            @else
                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                    title="This posting can no longer be edited once scheduling has started.">
                                <i class="bi bi-lock me-1"></i> Edit posting
                            </button>
                            @endif
                        </div>',
    'Panel-1 header: lock Edit posting once past step 2'
);

// ─── 3. Remove panelist button ────────────────────────────────────────────

apply_patch(
    $showPath,
    '                        @foreach ($panelists as $panelist)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span class="small fw-medium">{{ $panelist->name }}</span>
                            <form action="{{ route(\'job-postings.panelists.detach\', [$posting->id, $panelist->id]) }}"
                                  method="POST" class="m-0"
                                  onsubmit="return confirm(\'Remove {{ addslashes($panelist->name) }} from this posting\\\'s panel?\')">
                                @csrf @method(\'DELETE\')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </li>
                        @endforeach',
    '                        @foreach ($panelists as $panelist)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <span class="small fw-medium">{{ $panelist->name }}</span>
                            @if ($currentStep < 3)
                            <form action="{{ route(\'job-postings.panelists.detach\', [$posting->id, $panelist->id]) }}"
                                  method="POST" class="m-0"
                                  onsubmit="return confirm(\'Remove {{ addslashes($panelist->name) }} from this posting\\\'s panel?\')">
                                @csrf @method(\'DELETE\')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                            @endif
                        </li>
                        @endforeach',
    'Panelists card: lock Remove once past step 2'
);

// ─── 4/5. Panel-2 header (location dropdown) + applicant row (location tag, lock Check qualifications) ───

apply_patch(
    $showPath,
    '                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                    </div>

                    @forelse ($applications as $app)
                    @php
                        $qColors = [\'qualified\'=>\'success\',\'not_qualified\'=>\'danger\',\'hired\'=>\'dark\',\'ranking_sent\'=>\'primary\',\'interview_scheduled\'=>\'info\',\'submitted\'=>\'secondary\',\'rejected\'=>\'secondary\'];
                        $qColor = $qColors[$app->status] ?? \'secondary\';
                        $appCheck = $app->qualification_check ?? [];
                        // Candidate\'s self-reported qualifications — used only
                        // as the modal\'s starting point when no qualification
                        // check has been saved yet for that criterion; a saved
                        // "actual" value always takes precedence (see the
                        // qualCheckModal JS below). Same fields/precedence as
                        // the standalone /applications/{id} page.
                        $appSelfReported = [
                            \'education\'   => $app->candidate->education ?? null,
                            \'experience\'  => $app->candidate->years_experience ?? null,
                            \'training\'    => $app->candidate->training_hours ?? null,
                            \'eligibility\' => $app->candidate->eligibility ?? null,
                        ];
                    @endphp
                    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2" style="font-size:0.875rem;">
                        <div>
                            <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                            <div class="text-muted small">
                                Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge text-bg-{{ $qColor }}">
                                {{ str_replace(\'_\', \' \', ucfirst($app->status)) }}
                            </span>
                            @if (!in_array($app->status, [\'hired\', \'ranking_sent\']))
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
                            <form action="{{ route(\'applications.qualification-notice\', $app->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $app->qualification_notified_at ? \'Resend result\' : \'Email result\' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse',
    '                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0">
                            Qualification checking
                            <span class="badge text-bg-light text-dark border ms-1">{{ $applications->count() }}</span>
                        </h6>
                        @if ($locations->count() > 1)
                        <select id="qualLocationFilter" class="form-select form-select-sm" style="max-width:280px;">
                            <option value="">All places of assignment</option>
                            @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->place_of_assignment }} ({{ $applications->where(\'job_posting_location_id\', $loc->id)->count() }})</option>
                            @endforeach
                        </select>
                        @endif
                    </div>

                    @forelse ($applications as $app)
                    @php
                        $qColors = [\'qualified\'=>\'success\',\'not_qualified\'=>\'danger\',\'hired\'=>\'dark\',\'ranking_sent\'=>\'primary\',\'interview_scheduled\'=>\'info\',\'submitted\'=>\'secondary\',\'rejected\'=>\'secondary\'];
                        $qColor = $qColors[$app->status] ?? \'secondary\';
                        $appCheck = $app->qualification_check ?? [];
                        // Candidate\'s self-reported qualifications — used only
                        // as the modal\'s starting point when no qualification
                        // check has been saved yet for that criterion; a saved
                        // "actual" value always takes precedence (see the
                        // qualCheckModal JS below). Same fields/precedence as
                        // the standalone /applications/{id} page.
                        $appSelfReported = [
                            \'education\'   => $app->candidate->education ?? null,
                            \'experience\'  => $app->candidate->years_experience ?? null,
                            \'training\'    => $app->candidate->training_hours ?? null,
                            \'eligibility\' => $app->candidate->eligibility ?? null,
                        ];
                        // Looked up from the already-loaded $locations collection
                        // rather than assuming a specific relationship name exists
                        // on Application.
                        $appLocation = $locations->firstWhere(\'id\', $app->job_posting_location_id);
                    @endphp
                    <div class="border rounded p-3 mb-2 d-flex justify-content-between align-items-center flex-wrap gap-2"
                         style="font-size:0.875rem;" data-location-id="{{ $app->job_posting_location_id }}">
                        <div>
                            <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                            <div class="text-muted small">
                                Applied {{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format(\'M d, Y\') : \'—\' }}
                                @if ($appLocation)
                                    &middot; {{ $appLocation->place_of_assignment }}
                                @endif
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge text-bg-{{ $qColor }}">
                                {{ str_replace(\'_\', \' \', ucfirst($app->status)) }}
                            </span>
                            @if (!in_array($app->status, [\'hired\', \'ranking_sent\']) && $currentStep < 3)
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
                            <form action="{{ route(\'applications.qualification-notice\', $app->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    {{ $app->qualification_notified_at ? \'Resend result\' : \'Email result\' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <p class="text-muted small mb-0 text-center py-3">No applications yet for this posting.</p>
                    @endforelse',
    'Panel-2: add location filter dropdown, location tag per row, lock Check qualifications once past step 2'
);

// ─── 6. Panel-3: New schedule button ──────────────────────────────────────

apply_patch(
    $showPath,
    '                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Interview / exam schedules</h6>
                        <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                            <i class="bi bi-plus-lg me-1"></i> New schedule
                        </button>
                    </div>',
    '                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Interview / exam schedules</h6>
                        @if ($currentStep < 4)
                        <button class="btn btn-sm" style="background-color:var(--hr-primary);color:#fff;"
                                data-bs-toggle="modal" data-bs-target="#newScheduleModal">
                            <i class="bi bi-plus-lg me-1"></i> New schedule
                        </button>
                        @endif
                    </div>',
    'Panel-3: lock New schedule once past step 3'
);

// ─── 7. Panel-3: Delete schedule button ───────────────────────────────────

apply_patch(
    $showPath,
    '                                <td class="text-end">
                                    <form action="{{ route(\'interviews.destroy\', $s->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm(\'Delete this schedule?\')">
                                        @csrf @method(\'DELETE\')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>',
    '                                <td class="text-end">
                                    @if ($currentStep < 4)
                                    <form action="{{ route(\'interviews.destroy\', $s->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm(\'Delete this schedule?\')">
                                        @csrf @method(\'DELETE\')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                    @endif
                                </td>',
    'Panel-3: lock Delete schedule once past step 3'
);

// ─── 8. Panel-4: Remove criterion button ──────────────────────────────────

apply_patch(
    $showPath,
    '                                <form method="POST" action="{{ route(\'assessments.criteria.destroy\', $c->id) }}"
                                      onsubmit="return confirm(\'Remove this criterion?\')">
                                    @csrf @method(\'DELETE\')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                </form>',
    '                                @if ($posting->status !== \'closed\')
                                <form method="POST" action="{{ route(\'assessments.criteria.destroy\', $c->id) }}"
                                      onsubmit="return confirm(\'Remove this criterion?\')">
                                    @csrf @method(\'DELETE\')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                </form>
                                @endif',
    'Panel-4: lock Remove criterion once posting is closed'
);

// ─── 9. Panel-4: Add criterion button ─────────────────────────────────────

apply_patch(
    $showPath,
    '                    @if ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="No weight remaining">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif',
    '                    @if ($posting->status === \'closed\')
                    <button class="btn btn-sm btn-outline-secondary" disabled title="This posting is closed.">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @elseif ($remainingWeight > 0)
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addCriterionModal">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @else
                    <button class="btn btn-sm btn-outline-secondary" disabled title="No weight remaining">
                        <i class="bi bi-plus-lg me-1"></i> Add criterion
                    </button>
                    @endif',
    'Panel-4: lock Add criterion once posting is closed'
);

// ─── 10. Panel-4: Edit scores button ──────────────────────────────────────

apply_patch(
    $showPath,
    '                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                            data-application-id="{{ $cand->application_id }}"
                                            data-candidate-name="{{ $cand->candidate_name }}"
                                            data-scores="{{ json_encode($cand->scores) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>',
    '                                <td>
                                    @if ($posting->status !== \'closed\')
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#editScoresModal"
                                            data-application-id="{{ $cand->application_id }}"
                                            data-candidate-name="{{ $cand->candidate_name }}"
                                            data-scores="{{ json_encode($cand->scores) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endif
                                </td>',
    'Panel-4: lock Edit scores once posting is closed'
);

// ─── 11. JS: location filter ──────────────────────────────────────────────

apply_patch(
    $showPath,
    '// ── Panelist checklist for schedule modal ───────────────────────────────────
document.getElementById(\'schedAppSelect\')?.addEventListener(\'change\', function () {',
    '// ── Qualification checking: filter by place of assignment ──────────────────
document.getElementById(\'qualLocationFilter\')?.addEventListener(\'change\', function () {
    const val = this.value;
    document.querySelectorAll(\'#panel-2 [data-location-id]\').forEach(row => {
        row.style.display = (!val || row.dataset.locationId === val) ? \'\' : \'none\';
    });
});

// ── Panelist checklist for schedule modal ───────────────────────────────────
document.getElementById(\'schedAppSelect\')?.addEventListener(\'change\', function () {',
    'Add JS: location filter dropdown behavior'
);

echo "\n✅ Done.\n\n";
echo "Reload a job posting to check it. Send the assessment code whenever\n";
echo "you're ready and I'll build the per-job scheduling restructure + the\n";
echo "export-to-Excel button on Qualification Checking on top of this.\n\n";
echo "DELETE this script after running.\n";
