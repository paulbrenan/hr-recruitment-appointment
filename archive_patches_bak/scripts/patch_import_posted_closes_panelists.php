<?php
/**
 * patch_import_posted_closes_panelists.php
 *
 * Adds three things to the PDF import review screen, per-position-group,
 * matching what the manual posting form (JobPostingController) already
 * supports:
 *   - Posted date (posted_at)
 *   - Closing date (closes_at)
 *   - Panelist assignment -- checkboxes for the existing panelist pool,
 *     PLUS an inline "add new panelist" mini-list (name + optional email)
 *     that creates brand-new Panelist rows and assigns them, same as the
 *     manual form's new_panelist_names[]/new_panelist_emails[] pattern.
 *
 * The manual form's actual sync logic (JobPostingController::
 * syncPanelists()) is a private method on a different controller and
 * expects a single Request for one posting -- it can't be reused
 * directly here, where one request creates MANY postings at once. This
 * patch adds an equivalent private method scoped to
 * JobPostingImportController, operating on one row's array data instead.
 *
 * This patch's edits to the JobPosting::create() call are anchored on
 * small, stable lines (not the whole array block), so it works whether
 * or not fix_import_requirements_not_saved.php has been run yet --
 * order between the two doesn't matter.
 *
 * Run once from the project root:
 *   php patch_import_posted_closes_panelists.php
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

$importCtrl = __DIR__ . '/app/Http/Controllers/JobPostingImportController.php';
$reviewView = __DIR__ . '/resources/views/job-postings/import/review.blade.php';

// ── 1. JobPostingImportController.php ────────────────────────────────────

$c1old = <<<'OLD'
use App\Models\JobPosting;
use App\Models\JobPostingLocation;
use App\Models\PdfImportBatch;
use App\Jobs\ProcessPdfImportJob;
use Illuminate\Http\Request;
OLD;

$c1new = <<<'NEW'
use App\Models\JobPosting;
use App\Models\JobPostingLocation;
use App\Models\Panelist;
use App\Models\PdfImportBatch;
use App\Jobs\ProcessPdfImportJob;
use Illuminate\Http\Request;
NEW;

apply_patch($importCtrl, $c1old, $c1new, 'Import the Panelist model');

$c2old = <<<'OLD'
        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
            'requirements' => $batch->requirements ?? ['mandatory' => [], 'additional' => ''],
            'newlyRegisteredTitles' => $batch->newly_registered_titles ?? [],
        ]);
OLD;

$c2new = <<<'NEW'
        return view('job-postings.import.review', [
            'batch' => $batch,
            'grouped' => $grouped,
            'requirements' => $batch->requirements ?? ['mandatory' => [], 'additional' => ''],
            'newlyRegisteredTitles' => $batch->newly_registered_titles ?? [],
            'panelists' => Panelist::orderBy('name')->get(),
        ]);
NEW;

apply_patch($importCtrl, $c2old, $c2new, 'review(): pass the existing panelist pool to the view');

$c3old = <<<'OLD'
        $validated = $request->validate([
        'selected'                          => ['nullable', 'array'],
        'selected.*'                        => ['integer'],
        'rows'                              => ['required', 'array'],
        'rows.*.title'                      => ['nullable', 'string', 'max:255'],
        'rows.*.salary_grade'               => ['nullable', 'string', 'max:50'],
        'rows.*.vacancies'                  => ['nullable', 'integer', 'min:1'],
        'rows.*.location_place'             => ['nullable', 'array'],
        'rows.*.location_place.*'           => ['nullable', 'string', 'max:500'],
        'rows.*.qualification_education'    => ['nullable', 'string'],
        'rows.*.qualification_training'     => ['nullable', 'string'],
        'rows.*.qualification_experience'   => ['nullable', 'string'],
        'rows.*.qualification_eligibility'  => ['nullable', 'string'],
        'rows.*.duties_responsibilities'    => ['nullable', 'string'],
            ]);
OLD;

$c3new = <<<'NEW'
        $validated = $request->validate([
        'selected'                          => ['nullable', 'array'],
        'selected.*'                        => ['integer'],
        'rows'                              => ['required', 'array'],
        'rows.*.title'                      => ['nullable', 'string', 'max:255'],
        'rows.*.salary_grade'               => ['nullable', 'string', 'max:50'],
        'rows.*.vacancies'                  => ['nullable', 'integer', 'min:1'],
        'rows.*.location_place'             => ['nullable', 'array'],
        'rows.*.location_place.*'           => ['nullable', 'string', 'max:500'],
        'rows.*.qualification_education'    => ['nullable', 'string'],
        'rows.*.qualification_training'     => ['nullable', 'string'],
        'rows.*.qualification_experience'   => ['nullable', 'string'],
        'rows.*.qualification_eligibility'  => ['nullable', 'string'],
        'rows.*.duties_responsibilities'    => ['nullable', 'string'],
        'rows.*.posted_at'                  => ['nullable', 'date'],
        'rows.*.closes_at'                  => ['nullable', 'date'],
        'rows.*.panelist_ids'               => ['nullable', 'array'],
        'rows.*.panelist_ids.*'             => ['integer', 'exists:panelists,id'],
        'rows.*.new_panelist_names'         => ['nullable', 'array'],
        'rows.*.new_panelist_names.*'       => ['nullable', 'string', 'max:255'],
        'rows.*.new_panelist_emails'        => ['nullable', 'array'],
        'rows.*.new_panelist_emails.*'      => ['nullable', 'email', 'max:255'],
            ]);
NEW;

apply_patch($importCtrl, $c3old, $c3new, 'confirm(): validate posted_at/closes_at/panelist fields');

$c4old = <<<'OLD'
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
OLD;

$c4new = <<<'NEW'
                'duties_responsibilities' => $rowData['duties_responsibilities'] ?? null,
                'posted_at' => $rowData['posted_at'] ?? null,
                'closes_at' => $rowData['closes_at'] ?? null,
NEW;

apply_patch($importCtrl, $c4old, $c4new, "confirm(): save posted_at/closes_at on each created posting");

$c4bold = <<<'OLD'
                'status' => 'open',
            ]);

            if (!empty($locationRows)) {
OLD;

$c4bnew = <<<'NEW'
                'status' => 'open',
            ]);

            $this->syncImportPanelists($posting, $rowData);

            if (!empty($locationRows)) {
NEW;

apply_patch($importCtrl, $c4bold, $c4bnew, 'confirm(): sync panelists on each created posting');

$c5old = <<<'OLD'
        return redirect()
            ->route('job-postings.index')
            ->with('success', $message);
    }
}
OLD;

$c5new = <<<'NEW'
        return redirect()
            ->route('job-postings.index')
            ->with('success', $message);
    }

    /**
     * Panelist assignment for one imported posting, built from that row's
     * array data. Equivalent to JobPostingController::syncPanelists(),
     * which can't be reused directly here -- that method is private and
     * expects a single Request scoped to one posting, whereas confirm()
     * handles many rows (many postings) from one request at once.
     *
     * Expects, per row:
     *   panelist_ids[]        — checked existing panelist IDs to assign
     *   new_panelist_names[]  — names of brand-new panelists to create and assign
     *   new_panelist_emails[] — matching optional emails (same index as names)
     */
    private function syncImportPanelists(JobPosting $posting, array $rowData): void
    {
        $assignedIds = array_map('intval', $rowData['panelist_ids'] ?? []);

        $newNames  = $rowData['new_panelist_names'] ?? [];
        $newEmails = $rowData['new_panelist_emails'] ?? [];
        foreach ($newNames as $i => $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $email = trim($newEmails[$i] ?? '');
            $new = Panelist::create([
                'name'  => $name,
                'email' => $email !== '' ? $email : null,
            ]);
            $assignedIds[] = $new->id;
        }

        if (empty($assignedIds)) {
            return;
        }

        // Every assigned panelist is available, always -- same convention
        // as the manual posting form (no separate "available" toggle).
        $syncData = [];
        foreach (array_unique($assignedIds) as $panelistId) {
            $syncData[$panelistId] = ['is_available' => true];
        }

        $posting->panelists()->sync($syncData);
    }
}
NEW;

apply_patch($importCtrl, $c5old, $c5new, 'Add syncImportPanelists() helper');

// ── 2. review.blade.php ──────────────────────────────────────────────────

$v1old = <<<'OLD'
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status on import</label>
                    <input type="text" class="form-control form-control-sm" value="Open" disabled>
                </div>
OLD;

$v1new = <<<'NEW'
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status on import</label>
                    <input type="text" class="form-control form-control-sm" value="Open" disabled>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Posted date</label>
                    <input type="date" class="form-control form-control-sm" name="rows[{{ $i }}][posted_at]" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Closing date</label>
                    <input type="date" class="form-control form-control-sm" name="rows[{{ $i }}][closes_at]" min="{{ now()->toDateString() }}">
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Panelists</label>
                    <div class="border rounded p-2" style="background: #fafafa;">
                        @if ($panelists->isEmpty())
                            <div class="text-muted small mb-2">No panelists in the pool yet — add one below.</div>
                        @else
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach ($panelists as $p)
                            <label class="form-check form-check-inline border rounded px-2 py-1 small mb-0">
                                <input type="checkbox" class="form-check-input" name="rows[{{ $i }}][panelist_ids][]" value="{{ $p->id }}">
                                {{ $p->name }}
                            </label>
                            @endforeach
                        </div>
                        @endif
                        <table class="table table-sm mb-2 align-middle new-panelist-tbody-wrapper" style="font-size: 0.82rem;">
                            <tbody class="new-panelist-tbody" data-group="{{ $i }}"></tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-import-panelist" data-group="{{ $i }}">
                            <i class="bi bi-plus-lg me-1"></i> Add new panelist
                        </button>
                    </div>
                </div>
NEW;

apply_patch($reviewView, $v1old, $v1new, 'Add Posted date / Closing date / Panelists fields to each position group');

$v2old = <<<'OLD'
    // Init all existing rows on page load
    document.querySelectorAll('.location-import-row').forEach(initImportLocationRow);
OLD;

$v2new = <<<'NEW'
    // Init all existing rows on page load
    document.querySelectorAll('.location-import-row').forEach(initImportLocationRow);

    // ── New-panelist rows (name + optional email), added per group ────
    document.querySelectorAll('.add-import-panelist').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = this.dataset.group;
            const tbody = document.querySelector('.new-panelist-tbody[data-group="' + group + '"]');

            const row = document.createElement('tr');
            row.innerHTML =
                '<td style="width:45%;">' +
                    '<input type="text" class="form-control form-control-sm" ' +
                    'name="rows[' + group + '][new_panelist_names][]" placeholder="Panelist name">' +
                '</td>' +
                '<td style="width:45%;">' +
                    '<input type="email" class="form-control form-control-sm" ' +
                    'name="rows[' + group + '][new_panelist_emails][]" placeholder="Email (optional)">' +
                '</td>' +
                '<td class="text-center" style="width:10%;">' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-new-panelist" title="Remove"><i class="bi bi-x-lg"></i></button>' +
                '</td>';

            tbody.appendChild(row);
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-new-panelist');
        if (!btn) return;
        btn.closest('tr').remove();
    });
NEW;

apply_patch($reviewView, $v2old, $v2new, 'Add JS for the dynamic new-panelist rows');

echo "\nDone. Each imported posting group now has Posted date, Closing date, and\n";
echo "Panelist assignment (existing pool + inline new-panelist add) on the review\n";
echo "screen, saved to the created posting on confirm.\n";
