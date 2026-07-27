<?php

/**
 * patch_multi_location.php
 *
 * WHAT THIS DOES:
 *   1. Creates migration: job_posting_locations table
 *   2. Creates app/Models/JobPostingLocation.php
 *   3. Patches app/Models/JobPosting.php — adds locations() hasMany
 *   4. Patches JobPostingController:
 *      - index() eager-loads locations
 *      - create/edit pass locations
 *      - store/update sync locations table
 *      - show() eager-loads locations
 *   5. Patches form.blade.php — replaces place_of_assignment input + vacancies
 *      with a dynamic multi-row location table (dropdown + vacancy count)
 *   6. Patches index.blade.php — shows locations list instead of single field
 *   7. Patches show.blade.php — shows locations table
 *
 * HOW TO RUN:
 *   php patch_multi_location.php      (from project root)
 *   php artisan migrate               (required afterward)
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
    if ($count === 0) { echo "\n❌ PATCH ABORTED — content not found in $path\nLabel: $label\nSearched for:\n---\n$old\n---\n"; exit(1); }
    if ($count > 1)   { echo "\n❌ PATCH ABORTED — pattern found $count times in $path\nLabel: $label\n"; exit(1); }
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

echo "\n=== patch_multi_location.php ===\n\n";

// ─── 1. Migration ──────────────────────────────────────────────────────────

echo "[1] Creating migration...\n";

$migrationDir = ROOT . '/database/migrations';
if (!is_dir($migrationDir)) abort('database/migrations not found. Run from project root.');

$migrationFile = $migrationDir . '/' . date('Y_m_d_His') . '_create_job_posting_locations_table.php';

$migrationContent = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posting_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')
                  ->constrained('job_postings')
                  ->cascadeOnDelete();
            $table->text('place_of_assignment');
            $table->unsignedInteger('vacancies')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_locations');
    }
};
PHP;

write_new($migrationFile, $migrationContent, 'Migration: job_posting_locations');

// ─── 2. JobPostingLocation model ───────────────────────────────────────────

echo "\n[2] Creating app/Models/JobPostingLocation.php...\n";

$locationModel = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobPostingLocation extends Model
{
    protected $fillable = ['job_posting_id', 'place_of_assignment', 'vacancies'];

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }
}
PHP;

write_new(ROOT . '/app/Models/JobPostingLocation.php', $locationModel, 'JobPostingLocation model');

// ─── 3. JobPosting model — add locations() ─────────────────────────────────

echo "\n[3] Patching app/Models/JobPosting.php...\n";

$jobPostingPath = ROOT . '/app/Models/JobPosting.php';

// Add use statement for HasMany (already imported) and add relationship
apply_patch(
    $jobPostingPath,
    "use Illuminate\Database\Eloquent\Relations\HasMany;",
    "use Illuminate\Database\Eloquent\Relations\HasMany;",
    'JobPosting model: HasMany already imported — no change needed'
);

// Add locations() before panelists()
apply_patch(
    $jobPostingPath,
    "    public function panelists()",
    <<<'PHP'
    public function locations(): HasMany
    {
        return $this->hasMany(JobPostingLocation::class);
    }

    public function panelists()
PHP,
    'JobPosting model: locations() hasMany'
);

// Add JobPostingLocation to use statements (add after opening namespace block)
apply_patch(
    $jobPostingPath,
    "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\Relations\HasMany;",
    "use Illuminate\Database\Eloquent\Model;\nuse Illuminate\Database\Eloquent\Relations\HasMany;",
    'JobPosting model: imports already fine'
);

// ─── 4. JobPostingController ───────────────────────────────────────────────

echo "\n[4] Patching JobPostingController.php...\n";

$controllerPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

// 4a. Add JobPostingLocation use
apply_patch(
    $controllerPath,
    "use App\Models\Application;\nuse App\Models\JobPosting;\nuse App\Models\Panelist;",
    "use App\Models\Application;\nuse App\Models\JobPosting;\nuse App\Models\JobPostingLocation;\nuse App\Models\Panelist;",
    'Controller: add JobPostingLocation use'
);

// 4b. Remove 'place_of_assignment' and 'vacancies' from validation rules
// (they are now handled via locations, not the top-level columns)
apply_patch(
    $controllerPath,
    "            'place_of_assignment' => ['nullable', 'string', 'max:255'],",
    "            // place_of_assignment is now managed via job_posting_locations table",
    'Controller: remove place_of_assignment validation rule'
);

apply_patch(
    $controllerPath,
    "            'vacancies' => ['required', 'integer', 'min:1'],",
    "            // vacancies is now per-location in job_posting_locations table",
    'Controller: remove vacancies validation rule'
);

// 4c. index() — eager load locations
apply_patch(
    $controllerPath,
    "        \$postings = JobPosting::latest()->get();

        return view('job-postings.index', compact('postings'));",
    "        \$postings = JobPosting::with('locations')->latest()->get();

        return view('job-postings.index', compact('postings'));",
    'Controller: index() eager loads locations'
);

// 4d. create() — pass empty locations collection
apply_patch(
    $controllerPath,
    "        \$panelists         = Panelist::orderBy('name')->get();
        \$assignedPanelists = collect(); // empty for new posting

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists'));",
    "        \$panelists         = Panelist::orderBy('name')->get();
        \$assignedPanelists = collect(); // empty for new posting
        \$locations         = collect();

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists', 'locations'));",
    'Controller: create() passes empty locations'
);

// 4e. edit() — load existing locations
apply_patch(
    $controllerPath,
    "        \$panelists  = Panelist::orderBy('name')->get();
        // IDs of panelists already assigned to this posting, keyed by panelist_id => is_available
        \$assignedPanelists = \$posting->panelists()->get()->keyBy('id');

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists'));",
    "        \$panelists         = Panelist::orderBy('name')->get();
        \$assignedPanelists = \$posting->panelists()->get()->keyBy('id');
        \$locations         = \$posting->locations()->get();

        return view('job-postings.form', compact('posting', 'jobTitles', 'panelists', 'assignedPanelists', 'locations'));",
    'Controller: edit() loads existing locations'
);

// 4f. store() — sync locations after create
apply_patch(
    $controllerPath,
    "        \$posting = JobPosting::create(\$validated);

        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');",
    "        \$posting = JobPosting::create(\$validated);

        \$this->syncLocations(\$posting, \$request);
        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');",
    'Controller: store() syncs locations'
);

// 4g. update() — sync locations after update
apply_patch(
    $controllerPath,
    "        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');",
    "        \$this->syncLocations(\$posting, \$request);
        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting updated successfully.');",
    'Controller: update() syncs locations'
);

// 4h. show() — eager load locations
apply_patch(
    $controllerPath,
    "        \$posting = JobPosting::findOrFail(\$id);

        \$applications = Application::with('candidate')
            ->where('job_posting_id', \$id)
            ->latest('applied_at')
            ->get();

        \$panelists = \$posting->panelists()->orderBy('name')->get();

        return view('job-postings.show', compact('posting', 'applications', 'panelists'));",
    "        \$posting = JobPosting::findOrFail(\$id);

        \$applications = Application::with('candidate')
            ->where('job_posting_id', \$id)
            ->latest('applied_at')
            ->get();

        \$panelists = \$posting->panelists()->orderBy('name')->get();
        \$locations = \$posting->locations()->get();

        return view('job-postings.show', compact('posting', 'applications', 'panelists', 'locations'));",
    'Controller: show() loads locations'
);

// 4i. Add syncLocations() private method before syncPanelists()
apply_patch(
    $controllerPath,
    "    /**
     * Sync panelist assignments and availability from the form submission.",
    <<<'PHP'
    /**
     * Sync place-of-assignment locations from the form submission.
     * Expects parallel arrays:
     *   location_place[]    — place of assignment strings
     *   location_vacancies[] — vacancy count per place
     * Empty/blank place rows are skipped.
     */
    private function syncLocations(JobPosting $posting, \Illuminate\Http\Request $request): void
    {
        $places    = $request->input('location_place', []);
        $vacancies = $request->input('location_vacancies', []);

        // Delete all existing location rows for this posting then re-insert
        $posting->locations()->delete();

        $rows = [];
        foreach ($places as $i => $place) {
            $place = trim($place);
            if ($place === '') continue;

            $rows[] = [
                'job_posting_id'     => $posting->id,
                'place_of_assignment' => $place,
                'vacancies'          => max(1, (int) ($vacancies[$i] ?? 1)),
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        if (!empty($rows)) {
            JobPostingLocation::insert($rows);
        }

        // Keep the legacy place_of_assignment column in sync (first location)
        // so existing code that reads it doesn't break
        $first = $rows[0]['place_of_assignment'] ?? null;
        $totalVacancies = array_sum(array_column($rows, 'vacancies')) ?: 1;
        $posting->updateQuietly([
            'place_of_assignment' => $first,
            'vacancies'           => $totalVacancies,
        ]);
    }

    /**
     * Sync panelist assignments and availability from the form submission.
PHP,
    'Controller: syncLocations() private method'
);

// ─── 5. form.blade.php ─────────────────────────────────────────────────────

echo "\n[5] Patching form.blade.php...\n";

$formPath = ROOT . '/resources/views/job-postings/form.blade.php';

// Replace the old place_of_assignment + vacancies + salary grade row
$oldLocationBlock = <<<'BLADE'
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Place of assignment</label>
                    <div class="position-relative" id="schoolSearchWrapper">
                        <input
                            type="text"
                            class="form-control"
                            id="schoolSearchInput"
                            name="place_of_assignment"
                            autocomplete="off"
                            placeholder="Type to search schools or division units, or enter a new one..."
                            value="{{ old('place_of_assignment', $posting->place_of_assignment ?? '') }}"
                        >
                        <div
                            id="schoolSearchResults"
                            class="list-group position-absolute w-100 shadow-sm"
                            style="z-index: 1050; max-height: 220px; overflow-y: auto; display: none; top: 100%;"
                        ></div>
                        <div class="form-text" style="font-size: 0.72rem;">Pick from the list (schools or division units) or type one not yet listed.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Employment type</label>
                    <select class="form-select" name="employment_type">
                        @foreach (['Regular', 'Provisional', 'Casual', 'Job Order', 'On-the-Job Trainee'] as $type)
                            <option value="{{ $type }}" {{ ($posting->employment_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
BLADE;

$newLocationBlock = <<<'BLADE'
                <div class="col-12">
                    <label class="form-label small fw-medium mb-2 d-block">Places of assignment &amp; vacancies</label>
                    <div class="border rounded p-3">
                        <table class="table table-sm mb-2 align-middle" id="locationsTable">
                            <thead>
                                <tr>
                                    <th style="width: 70%;">Place of assignment</th>
                                    <th style="width: 20%;">Vacancies</th>
                                    <th style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody id="locationRows">
                                @php
                                    $locationRows = old('location_place')
                                        ? array_map(null, old('location_place', []), old('location_vacancies', []))
                                        : $locations->map(fn($l) => [$l->place_of_assignment, $l->vacancies])->toArray();
                                    // If no rows yet, start with one empty row
                                    if (empty($locationRows)) $locationRows = [['', 1]];
                                @endphp
                                @foreach ($locationRows as $i => [$place, $vac])
                                <tr class="location-row">
                                    <td>
                                        <div class="position-relative location-school-wrapper">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm location-school-input"
                                                name="location_place[]"
                                                autocomplete="off"
                                                placeholder="Search or type a school / unit..."
                                                value="{{ $place ?? '' }}"
                                            >
                                            <div class="list-group position-absolute w-100 shadow-sm location-school-results"
                                                 style="z-index: 1050; max-height: 200px; overflow-y: auto; display: none; top: 100%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" name="location_vacancies[]" value="{{ $vac ?? 1 }}" min="1" style="width: 80px;">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-location-btn" title="Remove row">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="addLocationBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add location
                        </button>
                        <div class="form-text mt-1" style="font-size: 0.72rem;">Each row is one place of assignment. Add as many as needed for this job title.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Employment type</label>
                    <select class="form-select" name="employment_type">
                        @foreach (['Regular', 'Provisional', 'Casual', 'Job Order', 'On-the-Job Trainee'] as $type)
                            <option value="{{ $type }}" {{ ($posting->employment_type ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
BLADE;

apply_patch($formPath, $oldLocationBlock, $newLocationBlock, 'form.blade.php: multi-location table');

// Remove the old standalone vacancies field (col-md-3)
apply_patch(
    $formPath,
    <<<'BLADE'
                <div class="col-md-3">
                    <label class="form-label small fw-medium">Vacancies</label>
                    <input type="number" class="form-control" name="vacancies" value="{{ old('vacancies', $posting->vacancies ?? 1) }}" min="1">
                </div>
BLADE,
    <<<'BLADE'
                {{-- Vacancies are now per-location below; this field is removed --}}
BLADE,
    'form.blade.php: remove standalone vacancies field'
);

// Replace the old school search JS with the new multi-row school search JS
$oldSchoolJs = <<<'BLADE'
    @php
        $placeOfAssignmentOptions = array_merge(config('schools.schools', []), config('schools.sdo_units', []));
    @endphp
    (function () {
        const schools = @json($placeOfAssignmentOptions);
        const schoolInput = document.getElementById('schoolSearchInput');
        const schoolResultsBox = document.getElementById('schoolSearchResults');
        const schoolWrapper = document.getElementById('schoolSearchWrapper');

        function renderSchoolResults(filter) {
            const query = filter.trim().toLowerCase();
            const matches = query === ''
                ? schools
                : schools.filter(s => s.toLowerCase().includes(query));

            schoolResultsBox.innerHTML = '';

            if (matches.length === 0) {
                schoolResultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (school) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small';
                item.textContent = school;
                item.addEventListener('click', function () {
                    schoolInput.value = school;
                    schoolResultsBox.style.display = 'none';
                });
                schoolResultsBox.appendChild(item);
            });

            schoolResultsBox.style.display = 'block';
        }

        schoolInput.addEventListener('input', function () {
            renderSchoolResults(schoolInput.value);
        });

        schoolInput.addEventListener('focus', function () {
            renderSchoolResults(schoolInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!schoolWrapper.contains(event.target)) {
                schoolResultsBox.style.display = 'none';
            }
        });
        // Note: no submit-blocking here -- typing a school not in the list is allowed.
    })();
BLADE;

$newSchoolJs = <<<'BLADE'
    @php
        $placeOfAssignmentOptions = array_merge(config('schools.schools', []), config('schools.sdo_units', []));
    @endphp
    // ── Multi-location school search ─────────────────────────────────────────
    const schoolOptions = @json($placeOfAssignmentOptions);

    function initLocationRow(row) {
        const input      = row.querySelector('.location-school-input');
        const resultsBox = row.querySelector('.location-school-results');
        const wrapper    = row.querySelector('.location-school-wrapper');

        if (!input || input._locationInited) return;
        input._locationInited = true;

        function render(filter) {
            const query   = filter.trim().toLowerCase();
            const matches = query === ''
                ? schoolOptions
                : schoolOptions.filter(s => s.toLowerCase().includes(query));

            resultsBox.innerHTML = '';

            if (matches.length === 0) {
                resultsBox.style.display = 'none';
                return;
            }

            matches.slice(0, 50).forEach(function (school) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action small py-1';
                item.textContent = school;
                item.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // prevent blur before click
                    input.value = school;
                    resultsBox.style.display = 'none';
                });
                resultsBox.appendChild(item);
            });

            resultsBox.style.display = 'block';
        }

        input.addEventListener('input',  () => render(input.value));
        input.addEventListener('focus',  () => render(input.value));
        input.addEventListener('blur',   () => setTimeout(() => { resultsBox.style.display = 'none'; }, 200));
    }

    // Init existing rows
    document.querySelectorAll('.location-row').forEach(initLocationRow);

    // Remove row
    document.getElementById('locationRows').addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-location-btn');
        if (!btn) return;
        const rows = document.querySelectorAll('.location-row');
        if (rows.length <= 1) {
            // Keep at least one row — just clear it
            const row = btn.closest('.location-row');
            row.querySelector('.location-school-input').value = '';
            row.querySelector('input[type="number"]').value = 1;
            return;
        }
        btn.closest('.location-row').remove();
    });

    // Add new row
    document.getElementById('addLocationBtn').addEventListener('click', function () {
        const tbody    = document.getElementById('locationRows');
        const template = tbody.querySelector('.location-row');
        const newRow   = template.cloneNode(true);

        // Clear values in the cloned row
        newRow.querySelector('.location-school-input').value = '';
        newRow.querySelector('.location-school-results').innerHTML = '';
        newRow.querySelector('.location-school-results').style.display = 'none';
        newRow.querySelector('input[type="number"]').value = 1;

        // Reset the init flag so initLocationRow wires it up fresh
        const clonedInput = newRow.querySelector('.location-school-input');
        clonedInput._locationInited = false;

        tbody.appendChild(newRow);
        initLocationRow(newRow);
        clonedInput.focus();
    });
BLADE;

apply_patch($formPath, $oldSchoolJs, $newSchoolJs, 'form.blade.php: replace school search JS with multi-row version');

// ─── 6. index.blade.php ────────────────────────────────────────────────────

echo "\n[6] Patching index.blade.php...\n";

$indexPath = ROOT . '/resources/views/job-postings/index.blade.php';

// Replace the single place_of_assignment cell with a locations list
apply_patch(
    $indexPath,
    "                    <td>{{ \$posting->place_of_assignment }}</td>",
    <<<'BLADE'
                    <td>
                        @if ($posting->locations->isNotEmpty())
                            <div class="d-flex flex-column gap-1">
                                @foreach ($posting->locations as $loc)
                                    <span class="small">{{ $loc->place_of_assignment }}
                                        <span class="text-muted">({{ $loc->vacancies }} {{ Str::plural('vacancy', $loc->vacancies) }})</span>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
BLADE,
    'index.blade.php: place of assignment → locations list'
);

// Remove the old Vacancies column header and cell
apply_patch(
    $indexPath,
    "                    <th class=\"text-center\">Vacancies</th>",
    "                    {{-- Vacancies now shown per-location in the Places column --}}",
    'index.blade.php: remove Vacancies column header'
);

apply_patch(
    $indexPath,
    "                    <td class=\"text-center\">{{ \$posting->vacancies }}</td>",
    "                    {{-- Vacancies shown per-location --}}",
    'index.blade.php: remove Vacancies column cell'
);

// Fix the Total vacancies summary card to sum across locations
apply_patch(
    $indexPath,
    "            <div class=\"fs-4 fw-semibold\">{{ \$postings->sum('vacancies') }}</div>",
    "            <div class=\"fs-4 fw-semibold\">{{ $postings->sum(fn($p) => $p->locations->sum('vacancies') ?: $p->vacancies) }}</div>",
    'index.blade.php: total vacancies card sums locations'
);

// ─── 7. show.blade.php ─────────────────────────────────────────────────────

echo "\n[7] Patching show.blade.php...\n";

$showPath = ROOT . '/resources/views/job-postings/show.blade.php';

// Replace single place_of_assignment + vacancies display with locations table
apply_patch(
    $showPath,
    <<<'BLADE'
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="text-muted small">Vacancies</div>
                <div class="fw-medium">{{ $posting->vacancies }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Posted</div>
                <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Closes</div>
                <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <span class="badge text-bg-success">{{ ucfirst($posting->status) }}</span>
            </div>
        </div>
BLADE,
    <<<'BLADE'
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="text-muted small">Posted</div>
                <div class="fw-medium">{{ $posting->posted_at ? \Carbon\Carbon::parse($posting->posted_at)->format('M d, Y') : '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Closes</div>
                <div class="fw-medium">{{ $posting->closes_at ? \Carbon\Carbon::parse($posting->closes_at)->format('M d, Y') : '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Status</div>
                <span class="badge text-bg-success">{{ ucfirst($posting->status) }}</span>
            </div>
        </div>

        {{-- Locations table --}}
        @if ($locations->isNotEmpty())
        <div class="mb-3">
            <div class="text-muted small mb-2">Places of assignment</div>
            <table class="table table-sm table-bordered mb-0" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Place of assignment</th>
                        <th class="text-center" style="width: 120px;">Vacancies</th>
                    </tr>
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
                        <td class="text-center">{{ $locations->sum('vacancies') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
BLADE,
    'show.blade.php: locations table replaces single vacancies row'
);

// ─── Done ──────────────────────────────────────────────────────────────────

echo <<<TEXT

✅ All patches applied.

NEXT STEPS:
  1. php artisan migrate
     → Creates job_posting_locations table

  2. Open any job posting → Edit:
     - The place of assignment + vacancies fields are replaced with a table
     - Each row has a school dropdown + vacancy count
     - "Add location" adds a new row
     - X button removes a row (keeps at least one)

  3. Save — the show page will display a locations table with a Total row.

  4. The index page now lists all locations per posting in the Places column.

  5. Old postings without locations will show "—" in the index until edited and re-saved.

  6. Delete this script.

NOTE for PDF import:
  When we get to Fix Import PDF Logic, the confirm step should create
  one job_posting_locations row per parsed (place, vacancies) pair
  instead of separate job postings per location.

TEXT;
