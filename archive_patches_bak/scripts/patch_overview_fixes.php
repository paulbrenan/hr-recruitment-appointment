<?php
/**
 * patch_overview_fixes.php
 *
 * Fixes 4 things in the Overview step (panel-1):
 *
 * 1. TRACKER BUG: isDone/isActive were computed from $currentStep (the
 *    status-driven LOCK boundary — 2 while status is "open"), not
 *    $activeStep (which panel is actually being viewed). That made Step 1
 *    show as "done" (green check) the instant status is "open", even while
 *    still sitting on Step 1 itself. Now both are computed from $activeStep;
 *    $currentStep is used only for the lock boundary, as intended.
 *
 * 2. REORDER: Qualification standards now render above Duties and
 *    responsibilities (previously duties came first).
 *
 * 3. DUTIES UX: previously a single <p style="white-space:pre-line"> dump of
 *    the raw duties text. Now parsed line-by-line into section headings
 *    (lines like "A. Personnel Administration"), bullet items (lines like
 *    "a. ...", "1. ...", or "- ...") and plain paragraphs — same visual
 *    language as the requirements checklist below it.
 *
 * 4. FULL JOB DETAILS: Overview now opens with the job title, salary grade,
 *    employment type, and status badge directly in the panel content itself
 *    (previously only in the compact sidebar), so Overview reads as the
 *    complete job view rather than a partial summary.
 *
 * HOW TO RUN:
 *   php patch_overview_fixes.php   (from project root)
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

echo "\n=== patch_overview_fixes.php ===\n\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// ─── 1. Tracker: isDone/isActive from $activeStep, not $currentStep ───────

apply_patch(
    $showPath,
    '                    @foreach ($steps as $num => $step)
                    @php
                        $isDone   = $currentStep > $num;
                        $isActive = $currentStep === $num;
                        $isLocked = $currentStep < $num;
                    @endphp',
    '                    @foreach ($steps as $num => $step)
                    @php
                        // isDone/isActive reflect which panel is actually being
                        // viewed ($activeStep), not the status-driven lock
                        // boundary ($currentStep) — otherwise step 1 shows as
                        // "done" the moment status is open, even while still
                        // sitting on step 1 itself.
                        $isDone   = $activeStep > $num;
                        $isActive = $activeStep === $num;
                        $isLocked = $currentStep < $num;
                    @endphp',
    'Tracker: isDone/isActive now driven by $activeStep'
);

apply_patch(
    $showPath,
    '                    @if ($num < 4)
                    <div style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $currentStep > $num ? \'#198754\' : \'#dee2e6\' }};"></div>
                    @endif',
    '                    @if ($num < 4)
                    <div style="width:3px;height:14px;margin-left:calc(0.5rem + 10px);
                                background:{{ $activeStep > $num ? \'#198754\' : \'#dee2e6\' }};"></div>
                    @endif',
    'Tracker: connector divider color now driven by $activeStep'
);

// ─── 2-4. Overview panel: job-details header + reorder + duties UX ────────

apply_patch(
    $showPath,
    '            {{-- Job details --}}
            <div class="card mb-3">
                <div class="card-body p-4">
                    <h6 class="mb-3">Posting details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Posted</div>
                            <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format(\'M d, Y\') : \'—\' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Closes</div>
                            <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format(\'M d, Y\') : \'—\' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Total vacancies</div>
                            <div class="fw-medium">{{ $locations->sum(\'vacancies\') ?: ($posting->vacancies ?? \'—\') }}</div>
                        </div>
                    </div>

                    @if ($locations->isNotEmpty())
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Places of assignment</div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="table-light">
                                <tr><th>Place</th><th class="text-center" style="width:100px;">Vacancies</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $loc)
                                <tr>
                                    <td>{{ $loc->place_of_assignment }}</td>
                                    <td class="text-center">{{ $loc->vacancies }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-medium">
                                    <td class="text-end text-muted small">Total</td>
                                    <td class="text-center">{{ $locations->sum(\'vacancies\') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if ($posting->duties_responsibilities)
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Duties and responsibilities</div>
                        <p class="small mb-0" style="white-space:pre-line;">{{ $posting->duties_responsibilities }}</p>
                    </div>
                    @endif

                    @if ($posting->qualification_education || $posting->qualification_training || $posting->qualification_experience || $posting->qualification_eligibility)
                    <div>
                        <div class="text-muted small mb-2">Qualification standards</div>
                        <div class="row g-2">
                            @foreach ([\'Education\' => $posting->qualification_education, \'Training\' => $posting->qualification_training, \'Experience\' => $posting->qualification_experience, \'Eligibility\' => $posting->qualification_eligibility] as $lbl => $val)
                            @if ($val)
                            <div class="col-md-6">
                                <div class="text-muted" style="font-size:0.75rem;">{{ $lbl }}</div>
                                <p class="small mb-1">{{ $val }}</p>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @php $mandatoryList = $posting->mandatoryRequirementsList(); $additionalList = $posting->additionalRequirementsList(); @endphp',
    '            {{-- Job details --}}
            <div class="card mb-3">
                <div class="card-body p-4">

                    {{-- Full job header — the complete job view, not a summary --}}
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">{{ $posting->title }}</h5>
                            <div class="text-muted small">
                                @if ($sg)<span class="me-2">{{ $sg }}</span>@endif
                                @if ($posting->employment_type)<span>&middot; {{ $posting->employment_type }}</span>@endif
                            </div>
                        </div>
                        <span class="badge text-bg-{{ $statusColors[$posting->status] ?? \'secondary\' }}">
                            {{ $statusLabels[$posting->status] ?? ucfirst($posting->status) }}
                        </span>
                    </div>
                    <hr class="mt-0">

                    <h6 class="mb-3">Posting details</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Posted</div>
                            <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format(\'M d, Y\') : \'—\' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Closes</div>
                            <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format(\'M d, Y\') : \'—\' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Total vacancies</div>
                            <div class="fw-medium">{{ $locations->sum(\'vacancies\') ?: ($posting->vacancies ?? \'—\') }}</div>
                        </div>
                    </div>

                    @if ($locations->isNotEmpty())
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Places of assignment</div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="table-light">
                                <tr><th>Place</th><th class="text-center" style="width:100px;">Vacancies</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $loc)
                                <tr>
                                    <td>{{ $loc->place_of_assignment }}</td>
                                    <td class="text-center">{{ $loc->vacancies }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-medium">
                                    <td class="text-end text-muted small">Total</td>
                                    <td class="text-center">{{ $locations->sum(\'vacancies\') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- Qualification standards — moved above duties --}}
                    @if ($posting->qualification_education || $posting->qualification_training || $posting->qualification_experience || $posting->qualification_eligibility)
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Qualification standards</div>
                        <div class="row g-2">
                            @foreach ([\'Education\' => $posting->qualification_education, \'Training\' => $posting->qualification_training, \'Experience\' => $posting->qualification_experience, \'Eligibility\' => $posting->qualification_eligibility] as $lbl => $val)
                            @if ($val)
                            <div class="col-md-6">
                                <div class="text-muted" style="font-size:0.75rem;">{{ $lbl }}</div>
                                <p class="small mb-1">{{ $val }}</p>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Duties and responsibilities — parsed into headings/bullets
                         instead of a raw pre-line text dump --}}
                    @if ($posting->duties_responsibilities)
                    <div class="mb-3">
                        <div class="text-muted small mb-2">Duties and responsibilities</div>
                        <div style="font-size:0.875rem;">
                            @php $dutyLines = preg_split(\'/\r\n|\r|\n/\', trim($posting->duties_responsibilities)); @endphp
                            @foreach ($dutyLines as $dutyLine)
                                @php $dutyLine = trim($dutyLine); @endphp
                                @continue(empty($dutyLine))
                                @if (preg_match(\'/^([A-Z])\.\s+(.*)$/\', $dutyLine, $m))
                                    <div class="fw-semibold mt-3 mb-1" style="color:var(--hr-primary,#0d6efd);">
                                        {{ $m[1] }}. {{ $m[2] }}
                                    </div>
                                @elseif (preg_match(\'/^([a-z]|\d+)\.\s+(.*)$/\', $dutyLine, $m))
                                    <div class="d-flex gap-2 mb-1 ps-2">
                                        <i class="bi bi-dot text-muted"></i>
                                        <span>{{ $m[2] }}</span>
                                    </div>
                                @elseif (preg_match(\'/^[•\-\*]\s*(.*)$/\', $dutyLine, $m))
                                    <div class="d-flex gap-2 mb-1 ps-2">
                                        <i class="bi bi-dot text-muted"></i>
                                        <span>{{ $m[1] }}</span>
                                    </div>
                                @else
                                    <p class="mb-1 ps-2">{{ $dutyLine }}</p>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @php $mandatoryList = $posting->mandatoryRequirementsList(); $additionalList = $posting->additionalRequirementsList(); @endphp',
    'Overview: full job header + qualifications-above-duties + UX-friendly duties parser'
);

echo "\n✅ Done.\n\n";
echo "Reload /job-postings/{id} — Overview should now show:\n";
echo "  - Title/SG/status header at the top of the panel content\n";
echo "  - Step 1 NOT checked while actively viewing step 1\n";
echo "  - Qualification standards above Duties and responsibilities\n";
echo "  - Duties rendered as headings/bullets instead of a raw text block\n\n";
echo "DELETE this script after running.\n";
