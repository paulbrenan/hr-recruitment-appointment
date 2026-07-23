<?php
/**
 * patch_offer_sg_override.php
 *
 * "Override compensation" in Offer Management was a bare peso number --
 * no way to say WHICH salary grade that number corresponds to, so an
 * overridden offer had no SG tied to it at all (only the job's inherited
 * SG was ever shown anywhere). Adds an "SG override" select next to the
 * compensation override field:
 *   - Left blank: behaves exactly as before (compensation defaults to the
 *     job's inherited SG at Step 1, or the typed peso override if any).
 *   - SG chosen: the peso field auto-fills with that SG's Step 1 amount
 *     (still manually editable afterward -- picking an SG is a shortcut,
 *     not a lock).
 *   - Both given: the typed peso amount wins (manual figure always takes
 *     priority), but the chosen SG is what's recorded in the success
 *     message so HR can tell which grade the offer(s) actually used.
 *
 * Run once from the project root:
 *   php patch_offer_sg_override.php
 * Then delete this file.
 */

function apply_patch($path, $old, $new, $label) {
    if (!file_exists($path)) {
        fwrite(STDERR, "[ABORT] File not found: $path ($label)\n");
        exit(1);
    }
    $contents = file_get_contents($path);
    if (strpos($contents, $old) === false) {
        fwrite(STDERR, "[ABORT] Expected content not found for: $label\n");
        fwrite(STDERR, "        File may already be patched or is a different version. No changes made.\n");
        exit(1);
    }
    copy($path, $path . '.bak');
    $updated = str_replace($old, $new, $contents, $count);
    if ($count !== 1) {
        fwrite(STDERR, "[ABORT] Expected exactly 1 match for '$label', found $count. Restoring backup.\n");
        copy($path . '.bak', $path);
        exit(1);
    }
    file_put_contents($path, $updated);
    echo "[OK] $label\n";
}

$showView  = __DIR__ . '/resources/views/job-postings/show.blade.php';
$offerCtrl = __DIR__ . '/app/Http/Controllers/JobOfferController.php';

// ── 1. show.blade.php: add SG override select + auto-fill JS ────────────

$v1old = <<<'OLD'
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Override compensation (optional)</label>
                                <input type="number" step="0.01" min="0" name="compensation_override" class="form-control form-control-sm"
                                       placeholder="Default: SG {{ $posting->salary_grade }} Step 1" value="{{ old('compensation_override') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Response deadline</label>
                                <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm w-100" style="background-color:var(--hr-primary);color:#fff;">
                                    Generate offer<span id="offerSelectedCountLabel"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <script>
                    (function () {
                        const limit = {{ (int) $offerVacancyLimit }};
                        const boxes = document.querySelectorAll('.offer-candidate-checkbox');
                        const countLabel = document.getElementById('offerSelectedCountLabel');

                        function refresh() {
                            const checked = document.querySelectorAll('.offer-candidate-checkbox:checked');
                            if (countLabel) countLabel.textContent = checked.length ? ' (' + checked.length + ')' : '';
                            const atLimit = checked.length >= limit;
                            boxes.forEach(function (b) {
                                if (!b.checked) b.disabled = atLimit;
                            });
                        }

                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();
                    })();
                    </script>
                    @endif
OLD;

$v1new = <<<'NEW'
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Override SG (optional)</label>
                                <select name="sg_override" id="offerSgOverrideSelect" class="form-select form-select-sm">
                                    <option value="">Inherit: SG {{ $posting->salary_grade }}</option>
                                    @for ($sgOpt = 1; $sgOpt <= 33; $sgOpt++)
                                        <option value="{{ $sgOpt }}" {{ old('sg_override') == $sgOpt ? 'selected' : '' }}>SG {{ $sgOpt }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Override compensation (optional)</label>
                                <input type="number" step="0.01" min="0" name="compensation_override" id="offerCompensationOverride" class="form-control form-control-sm"
                                       placeholder="Default: SG {{ $posting->salary_grade }} Step 1" value="{{ old('compensation_override') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">Response deadline</label>
                                <input type="date" name="response_deadline" class="form-control form-control-sm" min="{{ now()->toDateString() }}" value="{{ old('response_deadline') }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm w-100" style="background-color:var(--hr-primary);color:#fff;">
                                    Generate offer<span id="offerSelectedCountLabel"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <script>
                    (function () {
                        const limit = {{ (int) $offerVacancyLimit }};
                        const boxes = document.querySelectorAll('.offer-candidate-checkbox');
                        const countLabel = document.getElementById('offerSelectedCountLabel');

                        function refresh() {
                            const checked = document.querySelectorAll('.offer-candidate-checkbox:checked');
                            if (countLabel) countLabel.textContent = checked.length ? ' (' + checked.length + ')' : '';
                            const atLimit = checked.length >= limit;
                            boxes.forEach(function (b) {
                                if (!b.checked) b.disabled = atLimit;
                            });
                        }

                        boxes.forEach(function (b) { b.addEventListener('change', refresh); });
                        refresh();

                        // SG override -> auto-fill the peso field with that
                        // grade's Step 1 amount. Still just a starting
                        // point -- HR can edit the peso field afterward and
                        // that typed value always wins on submit.
                        const sgTable = @json(config('salary_grades.table'));
                        const sgOverrideSel = document.getElementById('offerSgOverrideSelect');
                        const compInput = document.getElementById('offerCompensationOverride');
                        sgOverrideSel?.addEventListener('change', function () {
                            const grade = parseInt(this.value, 10);
                            if (grade && sgTable[grade] && sgTable[grade][0] !== undefined) {
                                compInput.value = sgTable[grade][0];
                            }
                        });
                    })();
                    </script>
                    @endif
NEW;

apply_patch($showView, $v1old, $v1new, 'Step 5: add SG override select that auto-fills the compensation override field');

// ── 2. JobOfferController.php: accept sg_override, use it to resolve the
//       default compensation and reflect it in the success message ─────

$o1old = <<<'OLD'
        $validated = $request->validate([
            'job_posting_id'         => 'required|exists:job_postings,id',
            'application_ids'        => 'required|array|min:1',
            'application_ids.*'      => 'exists:applications,id|distinct',
            'compensation_override'  => 'nullable|numeric|min:0',
            'response_deadline'      => 'nullable|date|after_or_equal:today',
            'benefits'                => 'nullable|string',
            'terms'                   => 'nullable|string',
        ]);

        $posting = JobPosting::findOrFail($validated['job_posting_id']);
OLD;

$o1new = <<<'NEW'
        $validated = $request->validate([
            'job_posting_id'         => 'required|exists:job_postings,id',
            'application_ids'        => 'required|array|min:1',
            'application_ids.*'      => 'exists:applications,id|distinct',
            'sg_override'            => 'nullable|integer|min:1|max:33',
            'compensation_override'  => 'nullable|numeric|min:0',
            'response_deadline'      => 'nullable|date|after_or_equal:today',
            'benefits'                => 'nullable|string',
            'terms'                   => 'nullable|string',
        ]);

        $posting = JobPosting::findOrFail($validated['job_posting_id']);
NEW;

apply_patch($offerCtrl, $o1old, $o1new, 'store(): accept sg_override');

$o2old = <<<'OLD'
        $grade   = (int) $posting->salary_grade;
        $sgTable = config('salary_grades.table');
        $defaultCompensation = $sgTable[$grade][0] ?? $this->minCompensation(); // SG {grade} Step 1
        $compensation = $validated['compensation_override'] ?? $defaultCompensation;
OLD;

$o2new = <<<'NEW'
        // sg_override, if given, replaces the job's inherited SG as the
        // basis for the Step 1 default -- a typed compensation_override
        // still wins over both when present.
        $grade   = (int) ($validated['sg_override'] ?? $posting->salary_grade);
        $sgTable = config('salary_grades.table');
        $defaultCompensation = $sgTable[$grade][0] ?? $this->minCompensation(); // SG {grade} Step 1
        $compensation = $validated['compensation_override'] ?? $defaultCompensation;
NEW;

apply_patch($offerCtrl, $o2old, $o2new, 'store(): resolve compensation using sg_override (if given) instead of always the job\'s inherited SG');

$o3old = <<<'OLD'
        $overrideNote = isset($validated['compensation_override']) ? ', manually overridden' : ', SG ' . $grade . ' Step 1';
OLD;

$o3new = <<<'NEW'
        $overrideNote = isset($validated['compensation_override'])
            ? ', manually overridden (SG ' . $grade . ')'
            : ', SG ' . $grade . ' Step 1' . (isset($validated['sg_override']) ? ' (override)' : '');
NEW;

apply_patch($offerCtrl, $o3old, $o3new, 'store(): success message reflects the actual SG used, including when overridden');

echo "\nDone. Offer Management's override compensation field now has a matching SG\n";
echo "override select -- choosing an SG auto-fills the peso amount, and a typed peso\n";
echo "amount still wins over both on submit.\n";
