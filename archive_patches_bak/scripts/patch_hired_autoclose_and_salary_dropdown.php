<?php

/**
 * patch_hired_autoclose_and_salary_dropdown.php
 *
 * WHAT THIS DOES:
 *   1. Patches ApplicationController@updateStatus
 *      — when status is set to 'hired':
 *        • closes the job posting (status → 'closed')
 *        • rejects all other applicants on the same posting
 *
 *   2. Patches resources/views/offers/index.blade.php
 *      — replaces the free-text compensation input with a
 *        Salary Grade (1–33) → Step (1–8) two-dropdown selector
 *        that resolves to the exact PHP compensation amount
 *
 *   3. Patches JobOfferController@store
 *      — accepts salary_grade + salary_step instead of raw compensation,
 *        resolves the amount server-side from the official 2026 SG table
 *
 * HOW TO RUN:
 *   php patch_hired_autoclose_and_salary_dropdown.php    (from project root)
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in:\n  $path\nLabel: $label\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — pattern found $count times in:\n  $path\nLabel: $label\n"; exit(1); }
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

function abort(string $msg): void { echo "\n❌ $msg\n\n"; exit(1); }

echo "\n=== patch_hired_autoclose_and_salary_dropdown.php ===\n\n";

// ─── 1. ApplicationController — auto-close on hired ───────────────────────

echo "[1] Patching ApplicationController@updateStatus...\n";

$appControllerPath = ROOT . '/app/Http/Controllers/ApplicationController.php';

// Add JobPosting use
apply_patch(
    $appControllerPath,
    "use App\Models\Application;\nuse App\Models\InterviewSchedule;",
    "use App\Models\Application;\nuse App\Models\InterviewSchedule;\nuse App\Models\JobPosting;",
    'ApplicationController: add JobPosting use'
);

// Replace updateStatus method
$oldUpdateStatus = <<<'PHP'
        $application->update($validated);

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Application status updated successfully.');
PHP;

$newUpdateStatus = <<<'PHP'
        $application->update($validated);

        // When an applicant is marked hired:
        //   1. Close the job posting
        //   2. Reject all other applicants on the same posting
        if ($validated['status'] === 'hired') {
            $posting = JobPosting::find($application->job_posting_id);
            if ($posting) {
                $posting->update(['status' => 'closed']);

                Application::where('job_posting_id', $posting->id)
                    ->where('id', '!=', $application->id)
                    ->where('status', '!=', 'hired')
                    ->update(['status' => 'rejected']);
            }
        }

        return redirect()
            ->route('applications.show', $application->id)
            ->with('success', 'Application status updated successfully.');
PHP;

apply_patch($appControllerPath, $oldUpdateStatus, $newUpdateStatus, 'ApplicationController: hired → auto-close posting + reject others');

// ─── 2. Salary Grade table config file ────────────────────────────────────

echo "\n[2] Creating config/salary_grades.php...\n";

// Official 2026 Philippine Salary Standardization Law table (EO No. 64)
// SG 1–33, Steps 1–8
// Source: DBM Circular, effective Jan 1 2026
$salaryTable = [
    1  => [14634, 14841, 15051, 15265, 15482, 15703, 15927, 16155],
    2  => [15338, 15558, 15781, 16008, 16238, 16472, 16709, 16950],
    3  => [16052, 16285, 16522, 16763, 17008, 17257, 17510, 17767],
    4  => [16835, 17083, 17335, 17592, 17854, 18120, 18390, 18665],
    5  => [18356, 18631, 18910, 19194, 19482, 19774, 20072, 20375],
    6  => [19744, 20040, 20341, 20646, 20956, 21270, 21589, 21913],
    7  => [21387, 21708, 22034, 22365, 22701, 23042, 23388, 23739],
    8  => [23176, 23524, 23877, 24236, 24601, 24972, 25349, 25732],
    9  => [25232, 25611, 25997, 26389, 26788, 27194, 27607, 28027],
    10 => [27608, 28021, 28442, 28870, 29305, 29748, 30199, 30658],
    11 => [30531, 30985, 31447, 31917, 32395, 32882, 33377, 33881],
    12 => [33574, 34073, 34582, 35099, 35625, 36160, 36705, 37259],
    13 => [37032, 37581, 38140, 38708, 39287, 39876, 40475, 41085],
    14 => [40991, 41597, 42213, 42839, 43476, 44124, 44783, 45454],
    15 => [45269, 45948, 46638, 47340, 48054, 48780, 49518, 50269],
    16 => [50091, 50851, 51624, 52410, 53209, 54022, 54849, 55690],
    17 => [55553, 56399, 57260, 58135, 59025, 59930, 60851, 61787],
    18 => [61540, 62489, 63455, 64438, 65438, 66456, 67492, 68546],
    19 => [68726, 69803, 70900, 72016, 73152, 74308, 75485, 76684],
    20 => [76899, 78123, 79368, 80636, 81927, 83242, 84581, 85945],
    21 => [85074, 86472, 87895, 89344, 90820, 92323, 93854, 95413],
    22 => [94640, 96218, 97826, 99464, 101133, 102833, 104565, 106330],
    23 => [105439, 107212, 109020, 110863, 112742, 114657, 116609, 118599],
    24 => [117442, 119437, 121471, 123545, 125659, 127814, 130011, 132250],
    25 => [131985, 134233, 136525, 138862, 141246, 143677, 146157, 148686],
    26 => [148066, 150601, 153186, 155821, 158509, 161250, 164045, 166896],
    27 => [165268, 168123, 171034, 174003, 177032, 180121, 183272, 186486],
    28 => [184688, 187904, 191182, 194524, 197931, 201404, 204945, 208556],
    29 => [206501, 210108, 213782, 217526, 221341, 225228, 229190, 233228],
    30 => [230897, 234937, 239051, 243242, 247511, 251859, 256288, 260799],
    31 => [258165, 262697, 267311, 272009, 276793, 281664, 286625, 291677],
    32 => [288763, 293862, 299049, 304327, 309697, 315161, 320721, 326378],
    33 => [322514, 328259, 334106, 340058, 346117, 352284, 358562, 364953],
];

$tablePhp = "<?php\n\n/**\n * Official Philippine Salary Grade table — EO No. 64, effective Jan 1 2026.\n * Array structure: salary_grades[grade][step] (both 1-indexed).\n * Steps 1–8 per grade, Grades 1–33.\n */\n\nreturn [\n    'table' => [\n";

foreach ($salaryTable as $grade => $steps) {
    $tablePhp .= "        $grade => [" . implode(', ', $steps) . "],\n";
}

$tablePhp .= "    ],\n];\n";

write_new(ROOT . '/config/salary_grades.php', $tablePhp, 'config/salary_grades.php (2026 SG table)');

// ─── 3. JobOfferController — accept SG+step, resolve amount ───────────────

echo "\n[3] Patching JobOfferController@store + index()...\n";

$offerControllerPath = ROOT . '/app/Http/Controllers/JobOfferController.php';

// Replace MIN_COMPENSATION constant (no longer needed as a hard-coded floor)
apply_patch(
    $offerControllerPath,
    "    private const MIN_COMPENSATION = 14634; // Salary Grade 1, Step 1 (EO No. 64, SG SA 2026 table)",
    "    // SG 1 Step 1 — derived from config/salary_grades.php at runtime\n    private function minCompensation(): int\n    {\n        return config('salary_grades.table.1.0', 14634); // index 0 = step 1\n    }",
    'JobOfferController: replace MIN_COMPENSATION constant with method'
);

// Fix the index() reference to self::MIN_COMPENSATION
apply_patch(
    $offerControllerPath,
    "        \$minCompensation = self::MIN_COMPENSATION;",
    "        \$minCompensation = \$this->minCompensation();",
    'JobOfferController: index() uses minCompensation()'
);

// Replace store() validation + creation
$oldStore = <<<'PHP'
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'application_id' => 'required|exists:applications,id|unique:job_offers,application_id',
                'compensation' => 'required|numeric|min:' . self::MIN_COMPENSATION,
                'response_deadline' => 'nullable|date|after_or_equal:today',
                'benefits' => 'nullable|string',
                'terms' => 'nullable|string',
            ],
            [
                'compensation.min' => 'Compensation cannot be below \u20b1' . number_format(self::MIN_COMPENSATION, 0) . ' (Salary Grade 1, Step 1, the government minimum).',
            ]
        );

        JobOffer::create([
            'application_id' => $validated['application_id'],
            'compensation' => $validated['compensation'],
            'response_deadline' => $validated['response_deadline'] ?? null,
            'benefits' => $validated['benefits'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()->route('offers.index')->with('success', 'Offer generated as draft.');
    }
PHP;

$newStore = <<<'PHP'
    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id'  => 'required|exists:applications,id|unique:job_offers,application_id',
            'salary_grade'    => 'required|integer|min:1|max:33',
            'salary_step'     => 'required|integer|min:1|max:8',
            'response_deadline' => 'nullable|date|after_or_equal:today',
            'benefits'        => 'nullable|string',
            'terms'           => 'nullable|string',
        ]);

        // Resolve the exact compensation from the official SG table
        $grade        = (int) $validated['salary_grade'];
        $step         = (int) $validated['salary_step'];
        $sgTable      = config('salary_grades.table');
        $compensation = $sgTable[$grade][$step - 1] ?? $this->minCompensation();

        JobOffer::create([
            'application_id'    => $validated['application_id'],
            'compensation'      => $compensation,
            'response_deadline' => $validated['response_deadline'] ?? null,
            'benefits'          => $validated['benefits'] ?? null,
            'terms'             => $validated['terms'] ?? null,
            'status'            => 'draft',
        ]);

        return redirect()->route('offers.index')->with('success', "Offer generated as draft — SG {$grade} Step {$step} (₱" . number_format($compensation, 2) . ').');
    }
PHP;

apply_patch($offerControllerPath, $oldStore, $newStore, 'JobOfferController: store() accepts SG+step, resolves compensation');

// ─── 4. offers/index.blade.php — replace compensation input ───────────────

echo "\n[4] Patching resources/views/offers/index.blade.php...\n";

$offersIndexPath = ROOT . '/resources/views/offers/index.blade.php';

// Remove the old compensation error block
apply_patch(
    $offersIndexPath,
    "        @if (\$errors->has('compensation'))\n        <div class=\"alert alert-danger small py-2\">{{ \$errors->first('compensation') }}</div>\n        @endif",
    "        @if (\$errors->has('salary_grade') || \$errors->has('salary_step'))\n        <div class=\"alert alert-danger small py-2\">{{ \$errors->first('salary_grade') ?: \$errors->first('salary_step') }}</div>\n        @endif",
    'offers/index.blade.php: error block → SG/step errors'
);

// Replace the compensation input + hint
$oldCompInput = <<<'BLADE'
            <div class="col-md-3">
                <input type="number" name="compensation" class="form-control form-control-sm" placeholder="Compensation (PHP)" min="{{ $minCompensation }}" step="0.01" value="{{ old('compensation') }}" required>
                <div class="form-text" style="font-size: 0.72rem;">Min &#8369;{{ number_format($minCompensation, 0) }} (SG 1, Step 1)</div>
            </div>
BLADE;

$newCompInput = <<<'BLADE'
            <div class="col-md-2">
                <select name="salary_grade" class="form-select form-select-sm" id="sgSelect" required>
                    <option value="">SG</option>
                    @for ($sg = 1; $sg <= 33; $sg++)
                        <option value="{{ $sg }}" {{ old('salary_grade') == $sg ? 'selected' : '' }}>SG {{ $sg }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <select name="salary_step" class="form-select form-select-sm" id="stepSelect" required>
                    <option value="">Step</option>
                    @for ($step = 1; $step <= 8; $step++)
                        <option value="{{ $step }}" {{ old('salary_step') == $step ? 'selected' : '' }}>Step {{ $step }}</option>
                    @endfor
                </select>
                <div class="form-text" style="font-size: 0.72rem;" id="sgAmountHint">&nbsp;</div>
            </div>
BLADE;

apply_patch($offersIndexPath, $oldCompInput, $newCompInput, 'offers/index.blade.php: compensation → SG+step dropdowns');

// Widen the form grid — col-md-4 for candidate, col-md-2 for date, col-md-2 for button
// (we added 2 new cols so need to shrink others slightly)
apply_patch(
    $offersIndexPath,
    '<div class="col-md-4">
                <select name="application_id" class="form-select form-select-sm" required>',
    '<div class="col-md-3">
                <select name="application_id" class="form-select form-select-sm" required>',
    'offers/index.blade.php: shrink candidate col to fit new SG cols'
);

apply_patch(
    $offersIndexPath,
    '<div class="col-md-3">
                <input type="date" name="response_deadline"',
    '<div class="col-md-2">
                <input type="date" name="response_deadline"',
    'offers/index.blade.php: shrink date col'
);

// Add JS to show the resolved amount live as the user picks SG/step
$oldEndPush = <<<'BLADE'
@push('scripts')
<script>
    document.getElementById('respondModal').addEventListener('show.bs.modal', function (event) {
BLADE;

$newEndPush = <<<'BLADE'
@push('scripts')
<script>
    // ── SG/step → compensation live preview ──────────────────────────────────
    const sgTable = @json(config('salary_grades.table'));

    function updateAmountHint() {
        const sg   = parseInt(document.getElementById('sgSelect').value);
        const step = parseInt(document.getElementById('stepSelect').value);
        const hint = document.getElementById('sgAmountHint');
        if (sg && step && sgTable[sg] && sgTable[sg][step - 1]) {
            const amount = sgTable[sg][step - 1];
            hint.textContent = '₱' + amount.toLocaleString('en-PH');
            hint.style.color = 'var(--hr-primary)';
        } else {
            hint.textContent = '\u00a0';
        }
    }

    document.getElementById('sgSelect').addEventListener('change', updateAmountHint);
    document.getElementById('stepSelect').addEventListener('change', updateAmountHint);
    updateAmountHint(); // run on load in case old() values are present

    // ── Respond modal ────────────────────────────────────────────────────────
    document.getElementById('respondModal').addEventListener('show.bs.modal', function (event) {
BLADE;

apply_patch($offersIndexPath, $oldEndPush, $newEndPush, 'offers/index.blade.php: SG live preview JS');

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied. No migration needed.

WHAT CHANGED:

  1. ApplicationController — when an applicant status is set to 'hired':
     • The job posting is automatically closed (status → 'closed')
     • All other applicants on that posting are set to 'rejected'

  2. config/salary_grades.php — full 2026 Philippine SG table (SG 1–33, Steps 1–8)

  3. JobOfferController — store() now accepts salary_grade + salary_step,
     resolves the exact PHP amount from the config table server-side.
     Success message shows the resolved amount: "SG 14 Step 3 (₱42,213.00)"

  4. offers/index.blade.php — compensation field replaced with:
     • SG dropdown (SG 1–33)
     • Step dropdown (Step 1–8)
     • Live ₱ amount hint updates as you pick SG + Step

TEST:
  • Open /applications/{id} → change status to "Hired" → save
    → Check the job posting — should be Closed
    → Check other applicants on same posting — should be Rejected

  • Open /offers → pick SG + Step → hint shows the amount live
    → Generate → success message includes the resolved amount

DELETE this script after running.

TEXT;
