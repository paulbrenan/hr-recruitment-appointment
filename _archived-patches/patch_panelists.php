<?php

/**
 * patch_panelists.php
 *
 * WHAT THIS DOES:
 *   1. Creates migration: panelists table + job_posting_panelist pivot
 *      (seeds 6 mock panelists inside the migration)
 *   2. Creates app/Models/Panelist.php
 *   3. Patches app/Models/JobPosting.php — adds panelists() relationship
 *   4. Patches app/Http/Controllers/JobPostingController.php
 *      — edit() and show() load panelists
 *      — update() syncs pivot (availability checkboxes)
 *      — store() syncs pivot on create too
 *   5. Patches resources/views/job-postings/form.blade.php
 *      — adds Panelist section: name list, availability checkbox, delete, add new
 *   6. Patches resources/views/job-postings/show.blade.php
 *      — adds read-only panelist panel
 *
 * HOW TO RUN:
 *   php patch_panelists.php        (from project root)
 *   php artisan migrate            (required afterward)
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

// ─── helpers ───────────────────────────────────────────────────────────────

function backup(string $path): void {
    if (!file_exists($path)) return;
    $bak = $path . '.bak';
    $i = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    copy($path, $bak);
    echo "  [bak] $bak\n";
}

function apply_patch(string $path, string $old, string $new, string $label): void {
    if (!file_exists($path)) abort("File not found: $path");
    $content = file_get_contents($path);
    $count = substr_count($content, $old);
    if ($count === 0) abort("PATCH ABORTED — expected content not found in:\n  $path\nLabel: $label\nSearched for:\n---\n$old\n---");
    if ($count > 1)  abort("PATCH ABORTED — pattern found $count times (expected 1) in $path\nLabel: $label");
    backup($path);
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok ] $label\n";
}

function write_new(string $path, string $content, string $label): void {
    backup($path);
    file_put_contents($path, $content);
    echo "  [ok ] $label\n";
}

function abort(string $msg): void {
    echo "\n❌ $msg\n\n";
    exit(1);
}

echo "\n=== patch_panelists.php ===\n\n";

// ─── 1. Migration ──────────────────────────────────────────────────────────

echo "[1] Creating migration...\n";

$migrationDir = ROOT . '/database/migrations';
if (!is_dir($migrationDir)) abort("database/migrations not found. Run from project root.");

$migrationFile = $migrationDir . '/' . date('Y_m_d_His') . '_create_panelists_tables.php';

$migrationContent = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global panelist pool
        Schema::create('panelists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Pivot: which panelists are assigned to a posting and their availability
        Schema::create('job_posting_panelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('panelist_id')->constrained('panelists')->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->unique(['job_posting_id', 'panelist_id']);
            $table->timestamps();
        });

        // Seed 6 mock panelists
        DB::table('panelists')->insert([
            ['name' => 'Dr. Maria Santos',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Engr. Jose Reyes',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Prof. Ana Dela Cruz',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Atty. Ramon Villanueva','created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dr. Lourdes Mendoza',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mr. Carlos Bautista',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_panelist');
        Schema::dropIfExists('panelists');
    }
};
PHP;

write_new($migrationFile, $migrationContent, 'Migration: panelists + job_posting_panelist + 6 seeds');

// ─── 2. Panelist model ─────────────────────────────────────────────────────

echo "\n[2] Creating app/Models/Panelist.php...\n";

$panelistModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Panelist extends Model
{
    protected $fillable = ['name'];

    public function jobPostings()
    {
        return $this->belongsToMany(JobPosting::class, 'job_posting_panelist')
                    ->withPivot('is_available')
                    ->withTimestamps();
    }
}
PHP;

write_new(ROOT . '/app/Models/Panelist.php', $panelistModel, 'Panelist model');

// ─── 3. JobPosting model — add panelists() relationship ───────────────────

echo "\n[3] Patching app/Models/JobPosting.php...\n";

$jobPostingPath = ROOT . '/app/Models/JobPosting.php';
if (!file_exists($jobPostingPath)) abort("app/Models/JobPosting.php not found.");

$jobPostingContent = file_get_contents($jobPostingPath);

// Add relationship — append before the last closing brace
$relationship = <<<'PHP'

    public function panelists()
    {
        return $this->belongsToMany(Panelist::class, 'job_posting_panelist')
                    ->withPivot('is_available')
                    ->withTimestamps();
    }
PHP;

// Find the last closing brace and insert before it
if (substr_count($jobPostingContent, "\n}") === 0 && !str_ends_with(trim($jobPostingContent), '}')) {
    abort("Could not find closing brace in JobPosting.php to append relationship.");
}

backup($jobPostingPath);
// Append relationship before the final closing brace of the class
$patched = preg_replace('/(\n\})\s*$/', $relationship . "\n}", $jobPostingContent);
file_put_contents($jobPostingPath, $patched);
echo "  [ok ] JobPosting model: panelists() relationship\n";

// ─── 4. JobPostingController — edit/show/update/store ─────────────────────

echo "\n[4] Patching JobPostingController.php...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// 4a. Add Panelist use statement
apply_patch(
    $controllerPath,
    "use App\Models\Application;\nuse App\Models\JobPosting;",
    "use App\Models\Application;\nuse App\Models\JobPosting;\nuse App\Models\Panelist;",
    'Controller: add Panelist use statement'
);

// 4b. edit() — load all panelists + this posting's pivot data
$oldEdit = <<<'PHP'
    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->exists = true;
        $jobTitles = config('job_titles.titles', []);

        return view('job-postings.form', compact('posting', 'jobTitles'));
    }
PHP;

$newEdit = <<<'PHP'
    public function edit($id)
    {
        $posting = JobPosting::findOrFail($id);
        $posting->exists = true;
        $jobTitles  = config('job_titles.titles', []);
        $panelists  = Panelist::orderBy('name')->get();
        // IDs of panelists already assigned to this posting, keyed by panelist_id => is_available
        $assignedPanelists = $posting->panelists()->get()->keyBy('id');

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists'));
    }
PHP;

apply_patch($controllerPath, $oldEdit, $newEdit, 'Controller: edit() loads panelists');

// 4c. create() — load panelists for new posting form
$oldCreate = <<<'PHP'
    public function create()
    {
        $posting = new JobPosting();
        $posting->exists = false;
        $posting->mandatory_requirements = implode("\n", self::DEFAULT_MANDATORY_REQUIREMENTS);
        $jobTitles = config('job_titles.titles', []);

        return view('job-postings.form', compact('posting', 'jobTitles'));
    }
PHP;

$newCreate = <<<'PHP'
    public function create()
    {
        $posting = new JobPosting();
        $posting->exists = false;
        $posting->mandatory_requirements = implode("\n", self::DEFAULT_MANDATORY_REQUIREMENTS);
        $jobTitles         = config('job_titles.titles', []);
        $panelists         = Panelist::orderBy('name')->get();
        $assignedPanelists = collect(); // empty for new posting

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists'));
    }
PHP;

apply_patch($controllerPath, $oldCreate, $newCreate, 'Controller: create() loads panelists');

// 4d. store() — sync panelists after creating
$oldStore = <<<'PHP'
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        JobPosting::create($validated);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }
PHP;

$newStore = <<<'PHP'
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $posting = JobPosting::create($validated);

        $this->syncPanelists($posting, $request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }
PHP;

apply_patch($controllerPath, $oldStore, $newStore, 'Controller: store() syncs panelists');

// 4e. update() — sync panelists after updating
// Find the return redirect in update() specifically
$oldUpdateReturn = <<<'PHP'
        $posting->update($validated);

        // Cascade status to applications when the posting stage changes
        if ($oldStatus !== $newStatus) {
            $this->cascadeStatusToApplications($posting, $newStatus);
        }

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }
PHP;

$newUpdateReturn = <<<'PHP'
        $posting->update($validated);

        // Cascade status to applications when the posting stage changes
        if ($oldStatus !== $newStatus) {
            $this->cascadeStatusToApplications($posting, $newStatus);
        }

        $this->syncPanelists($posting, $request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }
PHP;

apply_patch($controllerPath, $oldUpdateReturn, $newUpdateReturn, 'Controller: update() syncs panelists');

// 4f. show() — load panelists for read-only display
$oldShow = <<<'PHP'
    public function show($id)
    {
        $posting = JobPosting::findOrFail($id);

        $applications = Application::with('candidate')
            ->where('job_posting_id', $id)
            ->latest('applied_at')
            ->get();

        return view('job-postings.show', compact('posting', 'applications'));
    }
PHP;

$newShow = <<<'PHP'
    public function show($id)
    {
        $posting = JobPosting::findOrFail($id);

        $applications = Application::with('candidate')
            ->where('job_posting_id', $id)
            ->latest('applied_at')
            ->get();

        $panelists = $posting->panelists()->orderBy('name')->get();

        return view('job-postings.show', compact('posting', 'applications', 'panelists'));
    }
PHP;

apply_patch($controllerPath, $oldShow, $newShow, 'Controller: show() loads panelists');

// 4g. Add syncPanelists() private method before hireApplicant()
$oldHireMethod = <<<'PHP'
    /**
     * Mark one applicant as Hired, reject all others on the same posting,
PHP;

$newHireMethod = <<<'PHP'
    /**
     * Sync panelist assignments and availability from the form submission.
     * Expects:
     *   panelist_ids[]        — checked panelist IDs to assign
     *   panelist_available[]  — panelist IDs that are marked available
     *   new_panelist_names[]  — names of brand-new panelists to create and assign
     */
    private function syncPanelists(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        // Create any newly added panelists
        $newNames = array_filter(array_map('trim', $request->input('new_panelist_names', [])));
        foreach ($newNames as $name) {
            if ($name !== '') {
                $new = Panelist::create(['name' => $name]);
                // Add to assigned list so they get synced below
                $request->merge([
                    'panelist_ids' => array_merge($request->input('panelist_ids', []), [$new->id]),
                    'panelist_available' => array_merge($request->input('panelist_available', []), [$new->id]),
                ]);
            }
        }

        $assignedIds   = array_map('intval', $request->input('panelist_ids', []));
        $availableIds  = array_map('intval', $request->input('panelist_available', []));

        // Build pivot data: assigned panelists with their availability flag
        $syncData = [];
        foreach ($assignedIds as $panelistId) {
            $syncData[$panelistId] = ['is_available' => in_array($panelistId, $availableIds)];
        }

        // sync() removes unassigned, adds new, updates existing
        $posting->panelists()->sync($syncData);
    }

    /**
     * Mark one applicant as Hired, reject all others on the same posting,
PHP;

apply_patch($controllerPath, $oldHireMethod, $newHireMethod, 'Controller: syncPanelists() private method');

// ─── 5. form.blade.php — panelist section ─────────────────────────────────

echo "\n[5] Patching form.blade.php...\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

// Insert panelist section just before the Save/Cancel buttons
$oldButtons = <<<'BLADE'
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save posting</button>
                <a href="{{ route('job-postings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
BLADE;

$newButtons = <<<'BLADE'
                {{-- ── Panelist / Interview Panel ─────────────────────────── --}}
                <div class="col-12">
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="small fw-medium text-muted">Interview Panel / Ranking Committee</div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addPanelistBtn">
                                <i class="bi bi-plus-lg me-1"></i> Add panelist
                            </button>
                        </div>

                        {{-- New panelist name inputs (added dynamically) --}}
                        <div id="newPanelistInputs"></div>

                        {{-- Global panelist list --}}
                        @if ($panelists->isEmpty())
                            <p class="text-muted small mb-0" id="emptyPanelistMsg">No panelists in the system yet. Use "Add panelist" to create one.</p>
                        @else
                            <p class="text-muted small mb-2" style="font-size: 0.72rem;">Check a panelist to assign them to this posting. Tick "Available" if they are available for this schedule.</p>
                            <ul class="list-group" id="panelistList">
                                @foreach ($panelists as $panelist)
                                    @php
                                        $assigned  = isset($assignedPanelists[$panelist->id]);
                                        $available = $assigned && $assignedPanelists[$panelist->id]->pivot->is_available;
                                    @endphp
                                    <li class="list-group-item d-flex align-items-center gap-3 py-2" id="panelistRow{{ $panelist->id }}">
                                        {{-- Assign checkbox --}}
                                        <input
                                            type="checkbox"
                                            class="form-check-input panelist-assign-cb mt-0"
                                            name="panelist_ids[]"
                                            value="{{ $panelist->id }}"
                                            id="panelist{{ $panelist->id }}"
                                            {{ $assigned ? 'checked' : '' }}
                                        >
                                        {{-- Name (editable inline) --}}
                                        <label class="form-check-label flex-grow-1 mb-0" for="panelist{{ $panelist->id }}" style="cursor: pointer;">
                                            <span class="panelist-name-display">{{ $panelist->name }}</span>
                                        </label>
                                        {{-- Available checkbox — only active when assigned --}}
                                        <div class="d-flex align-items-center gap-1">
                                            <input
                                                type="checkbox"
                                                class="form-check-input panelist-avail-cb mt-0"
                                                name="panelist_available[]"
                                                value="{{ $panelist->id }}"
                                                id="avail{{ $panelist->id }}"
                                                {{ $available ? 'checked' : '' }}
                                                {{ !$assigned ? 'disabled' : '' }}
                                            >
                                            <label class="form-check-label small text-muted mb-0" for="avail{{ $panelist->id }}">Available</label>
                                        </div>
                                        {{-- Delete button (calls a JS confirm; uses a hidden form) --}}
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link text-danger p-0 ms-1 panelist-delete-btn"
                                            data-panelist-id="{{ $panelist->id }}"
                                            data-panelist-name="{{ $panelist->name }}"
                                            title="Remove panelist from system"
                                        ><i class="bi bi-trash"></i></button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save posting</button>
                <a href="{{ route('job-postings.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
BLADE;

apply_patch($formPath, $oldButtons, $newButtons, 'form.blade.php: panelist section');

// Add JS for panelist interactions before closing @endpush/@endsection
$oldEndPush = <<<'BLADE'
    initRequirementList('mandatoryList', 'mandatoryInput', 'mandatoryAddBtn', 'mandatoryHidden');
    initRequirementList('additionalList', 'additionalInput', 'additionalAddBtn', 'additionalHidden');
</script>
@endpush
@endsection
BLADE;

$newEndPush = <<<'BLADE'
    initRequirementList('mandatoryList', 'mandatoryInput', 'mandatoryAddBtn', 'mandatoryHidden');
    initRequirementList('additionalList', 'additionalInput', 'additionalAddBtn', 'additionalHidden');

    // ── Panelist JS ──────────────────────────────────────────────────────────

    // When assign checkbox is unchecked, disable the Available checkbox too
    document.querySelectorAll('.panelist-assign-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            const row      = document.getElementById('panelistRow' + this.value);
            const availCb  = row ? row.querySelector('.panelist-avail-cb') : null;
            if (availCb) {
                availCb.disabled = !this.checked;
                if (!this.checked) availCb.checked = false;
            }
        });
    });

    // Add new panelist input row
    let newPanelistCount = 0;
    document.getElementById('addPanelistBtn').addEventListener('click', function () {
        newPanelistCount++;
        const wrapper = document.getElementById('newPanelistInputs');
        const div = document.createElement('div');
        div.className = 'input-group input-group-sm mb-2';
        div.innerHTML = `
            <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
            <input type="text" class="form-control" name="new_panelist_names[]" placeholder="New panelist name..." autocomplete="off">
            <button type="button" class="btn btn-outline-danger" onclick="this.closest('.input-group').remove()">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        wrapper.appendChild(div);
        div.querySelector('input').focus();
    });

    // Delete panelist from system (submits a hidden DELETE form via JS)
    document.querySelectorAll('.panelist-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = this.dataset.panelistName;
            const id   = this.dataset.panelistId;
            if (!confirm('Remove "' + name + '" from the panelist pool? This cannot be undone.')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/panelists/' + id;
            form.innerHTML = `
                @csrf
                <input type="hidden" name="_method" value="DELETE">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });
</script>
@endpush
@endsection
BLADE;

apply_patch($formPath, $oldEndPush, $newEndPush, 'form.blade.php: panelist JS');

// ─── 6. show.blade.php — read-only panelist panel ─────────────────────────

echo "\n[6] Patching show.blade.php...\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// Insert panelist card between the posting details card and the applications card
$oldShowCard = <<<'BLADE'
<div class="card">
    <div class="card-body p-4">
        <h6 class="mb-3">Applications for this posting</h6>
BLADE;

$newShowCard = <<<'BLADE'
@if ($panelists->isNotEmpty())
<div class="card mb-3">
    <div class="card-body p-4">
        <h6 class="mb-3">Interview Panel / Ranking Committee</h6>
        <ul class="list-group">
            @foreach ($panelists as $panelist)
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <span class="small fw-medium">{{ $panelist->name }}</span>
                @if ($panelist->pivot->is_available)
                    <span class="badge text-bg-success">Available</span>
                @else
                    <span class="badge text-bg-secondary">Unavailable</span>
                @endif
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body p-4">
        <h6 class="mb-3">Applications for this posting</h6>
BLADE;

apply_patch($showPath, $oldShowCard, $newShowCard, 'show.blade.php: panelist panel');

// ─── 7. Routes — panelist delete + rename ─────────────────────────────────

echo "\n[7] Adding panelist routes to routes/web.php...\n";

$webPath = ROOT . '/routes/web.php';

// Add PanelistController use statement
apply_patch(
    $webPath,
    "use App\Http\Controllers\RankingController;",
    "use App\Http\Controllers\RankingController;\nuse App\Http\Controllers\PanelistController;",
    'web.php: add PanelistController use'
);

// Add routes at the end of job postings block
apply_patch(
    $webPath,
    "// Mark one applicant as hired → rejects all others on same posting + closes posting\nRoute::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])->name('job-postings.hire');",
    "// Mark one applicant as hired → rejects all others on same posting + closes posting\nRoute::post('/job-postings/{postingId}/hire/{applicationId}', [JobPostingController::class, 'hireApplicant'])->name('job-postings.hire');\n\n// Panelist pool management\nRoute::put('/panelists/{id}', [PanelistController::class, 'update'])->name('panelists.update');\nRoute::delete('/panelists/{id}', [PanelistController::class, 'destroy'])->name('panelists.destroy');",
    'web.php: panelist routes'
);

// ─── 8. PanelistController ─────────────────────────────────────────────────

echo "\n[8] Creating app/Http/Controllers/PanelistController.php...\n";

$panelistController = <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\Panelist;
use Illuminate\Http\Request;

class PanelistController extends Controller
{
    /**
     * Rename a panelist (called via AJAX or form — currently via form redirect back).
     */
    public function update(Request $request, $id)
    {
        $panelist = Panelist::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $panelist->update(['name' => $request->input('name')]);

        return redirect()->back()->with('success', 'Panelist name updated.');
    }

    /**
     * Delete a panelist from the global pool.
     * The cascade on job_posting_panelist will remove pivot rows automatically.
     */
    public function destroy($id)
    {
        $panelist = Panelist::findOrFail($id);
        $panelist->delete();

        return redirect()->back()->with('success', 'Panelist removed.');
    }
}
PHP;

write_new(ROOT . '/app/Http/Controllers/PanelistController.php', $panelistController, 'PanelistController');

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied.

NEXT STEPS (in order):
  1. php artisan migrate
     → Creates panelists + job_posting_panelist tables, seeds 6 mock panelists

  2. Open any job posting edit page — the panelist panel should appear
     at the bottom with the 6 mock names, assign checkboxes, and availability toggles.

  3. Test: check a few panelists, mark some available/unavailable, save.
     Open the show page — the panel should display with Available/Unavailable badges.

  4. Test delete: trash icon removes the panelist from the system globally.

  5. Test add: "Add panelist" button adds an input row; fill a name and save
     — the new panelist is created and assigned to this posting.

  6. Delete this script.

TEXT;
