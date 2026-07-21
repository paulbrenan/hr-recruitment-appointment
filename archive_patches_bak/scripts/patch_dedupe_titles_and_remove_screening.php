<?php
/**
 * patch_dedupe_titles_and_remove_screening.php
 *
 * 1. DUPLICATE TITLE MERGE: store() previously created a brand new
 *    JobPosting every time, even if one with the same title already
 *    existed. Now: if a posting with that exact title already exists,
 *    the submitted location_place[]/location_vacancies[] rows are merged
 *    into its existing locations instead of creating a duplicate posting
 *    -- same place again increments that location's vacancy count, a new
 *    place gets added as a new location row (same convention the PDF
 *    import pipeline already uses for schools appearing more than once
 *    in a table). HR is redirected to the EXISTING posting, not a new one.
 *
 * 2. REMOVE "screening": ApplicationController::updateStatus()'s allowed
 *    status list still included 'screening', and JobPostingController had
 *    a stale docblock line documenting an 'open → screening' style mapping
 *    that doesn't actually exist in cascadeStatusToApplications()'s $map
 *    (the map itself never had a 'screening' entry -- this is a doc-only
 *    fix there). Removed 'screening' from the validation list so it can no
 *    longer be set at all.
 *
 * HOW TO RUN:
 *   php patch_dedupe_titles_and_remove_screening.php   (project root)
 * DELETE this script after running.
 *
 * STILL TO COME (deferred until I have the full show.blade.php and the
 * assessment code you're sending): step-lock-can't-go-back enforcement,
 * the qualification-checking place-of-assignment dropdown, per-job
 * scheduling restructure, and copying the assessment export/import
 * features.
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

echo "\n=== patch_dedupe_titles_and_remove_screening.php ===\n\n";

// ─── 1. JobPostingController: dedupe by title, merge locations ───────────

echo "[1] JobPostingController.php\n";

$jpPath = ROOT . '/app/Http/Controllers/JobPostingController.php';

apply_patch(
    $jpPath,
    "    public function store(Request \$request)
    {
        \$validated = \$request->validate(\$this->rules());

        \$posting = JobPosting::create(\$validated);

        \$this->syncLocations(\$posting, \$request);
        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }",
    "    public function store(Request \$request)
    {
        \$validated = \$request->validate(\$this->rules());

        // Don't create a duplicate posting for a title that already
        // exists -- merge the submitted place(s) of assignment into the
        // existing posting's locations instead (same place again adds to
        // its vacancy count, a new place becomes a new location row; same
        // convention the PDF import pipeline uses).
        \$existing = JobPosting::where('title', \$validated['title'])->first();

        if (\$existing) {
            \$this->mergeLocationsInto(\$existing, \$request);

            return redirect()
                ->route('job-postings.show', \$existing->id)
                ->with('success', 'A posting for \"' . \$existing->title . '\" already exists -- the place(s) of assignment you entered were added to it instead of creating a duplicate.');
        }

        \$posting = JobPosting::create(\$validated);

        \$this->syncLocations(\$posting, \$request);
        \$this->syncPanelists(\$posting, \$request);

        return redirect()
            ->route('job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }

    /**
     * Merge location_place[]/location_vacancies[] from a create-form
     * submission into an ALREADY-EXISTING posting, without touching its
     * current locations (unlike syncLocations(), which wholesale replaces
     * them -- that's correct for editing a posting's own form, but wrong
     * here since we're adding to a different, pre-existing posting).
     * Same place submitted again increments that location's vacancy count
     * rather than creating a second row for it.
     */
    private function mergeLocationsInto(JobPosting \$posting, \Illuminate\Http\Request \$request): void
    {
        \$places    = \$request->input('location_place', []);
        \$vacancies = \$request->input('location_vacancies', []);

        \$existingLocations = \$posting->locations()->get()->keyBy('place_of_assignment');

        foreach (\$places as \$i => \$place) {
            \$place = trim(\$place);
            if (\$place === '') continue;

            \$addVacancies = max(1, (int) (\$vacancies[\$i] ?? 1));

            \$existingLocation = \$existingLocations->get(\$place);
            if (\$existingLocation) {
                \$existingLocation->increment('vacancies', \$addVacancies);
            } else {
                \$newLocation = \$posting->locations()->create([
                    'place_of_assignment' => \$place,
                    'vacancies'           => \$addVacancies,
                ]);
                \$existingLocations->put(\$place, \$newLocation);
            }
        }

        // Keep the legacy place_of_assignment/vacancies columns in sync,
        // same convention syncLocations() uses.
        \$posting->refresh();
        \$allLocations = \$posting->locations()->get();
        \$posting->updateQuietly([
            'place_of_assignment' => \$allLocations->first()?->place_of_assignment,
            'vacancies'           => \$allLocations->sum('vacancies') ?: 1,
        ]);
    }",
    'store(): dedupe by title, merge locations into existing posting'
);

apply_patch(
    $jpPath,
    "    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   screening           → screening
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → rejected  (for all non-hired applicants)",
    "    /**
     * Map job posting pipeline stage → application status, then bulk-update.
     *
     * Mapping:
     *   open                → submitted
     *   interview_scheduled → interview_scheduled
     *   ranking             → ranked
     *   closed              → rejected  (for all non-hired applicants)",
    'Remove stale screening mapping from docblock'
);

// ─── 2. ApplicationController: remove screening from allowed statuses ────

echo "\n[2] ApplicationController.php\n";

$appPath = ROOT . '/app/Http/Controllers/ApplicationController.php';

apply_patch(
    $appPath,
    "'status' => ['required', 'in:submitted,screening,shortlisted,interview_scheduled,assessed,ranked,qualified,not_qualified,offer_sent,offer_accepted,offer_declined,hired,rejected'],",
    "'status' => ['required', 'in:submitted,shortlisted,interview_scheduled,assessed,ranked,qualified,not_qualified,offer_sent,offer_accepted,offer_declined,hired,rejected'],",
    "updateStatus(): remove 'screening' from allowed status list"
);

echo "\n✅ Done.\n\n";
echo "NOTE: any existing Application rows that already have status =\n";
echo "'screening' are untouched by this patch (won't error, just an\n";
echo "already-existing value) -- let me know if you want those migrated to\n";
echo "something else.\n\n";
echo "DELETE this script after running.\n";
