<?php
/**
 * remove_place_of_assignment.php
 *
 * One-shot patch: removes the "Place of Assignment" dependent field from
 * the public registration form. A position now shows/hides on the
 * register page based ONLY on:
 *   1. hasAnyOpenVacancy() — true only while at least one location (or,
 *      for legacy postings, the single vacancies column) still has an
 *      unhired slot. Already summed across all locations, so filling
 *      every place makes the whole posting disappear automatically.
 *   2. closes_at not passed — a position whose due date has lapsed is
 *      excluded even if vacancies remain.
 *
 * Run from the Laravel project root:
 *   php remove_place_of_assignment.php
 *
 * Targets (edit the paths below if your project layout differs):
 *   app/Http/Controllers/CandidateAuthController.php
 *   resources/views/portal/register.blade.php
 *
 * Creates a .bak of each file before editing. Aborts (no changes written)
 * if any expected anchor text isn't found, so it never leaves a file
 * half-patched.
 */

$controllerPath = __DIR__ . '/app/Http/Controllers/CandidateAuthController.php';
$bladePath       = __DIR__ . '/resources/views/portal/register.blade.php';

function fail(string $msg): void
{
    fwrite(STDERR, "ABORTED: {$msg}\n");
    exit(1);
}

function backup(string $path): void
{
    $bak = $path . '.bak';
    if (!copy($path, $bak)) {
        fail("Could not create backup for {$path}");
    }
    echo "  backed up -> {$bak}\n";
}

// ─────────────────────────────────────────────────────────────────────────
// 1. CandidateAuthController.php
// ─────────────────────────────────────────────────────────────────────────
if (!file_exists($controllerPath)) {
    fail("Controller not found at {$controllerPath}. Edit \$controllerPath at the top of this script.");
}
$controller = file_get_contents($controllerPath);
$originalController = $controller;

echo "Patching CandidateAuthController.php...\n";

// --- 1a. showRegister(): drop the per-location hired_count eager-load,
//         add a closes_at cutoff to the open-postings filter chain.
$oldShowRegister = <<<'PHP'
        // Eager-load each location's hired count (aliased as hired_count)
        // in one query so JobPostingLocation::isFilled() doesn't have to
        // hit the DB again per location.
        $openPostings = JobPosting::where('status', 'open')
            ->with(['locations' => function ($query) {
                $query->withCount(['applications as hired_count' => function ($q) {
                    $q->where('status', 'hired');
                }])->orderBy('place_of_assignment');
            }])
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();
PHP;

$newShowRegister = <<<'PHP'
        // Place of Assignment is no longer chosen on the public form --
        // a posting simply disappears from the list once EITHER:
        //   - every location (or the legacy vacancies column) is filled
        //     (hasAnyOpenVacancy() sums across all locations already), or
        //   - its closes_at due date has passed.
        // Locations are still eager-loaded (needed by hasAnyOpenVacancy()),
        // just without the per-location hired_count/order-by that only
        // existed to feed the old Place dropdown.
        $openPostings = JobPosting::where('status', 'open')
            ->where(function ($query) {
                $query->whereNull('closes_at')
                      ->orWhereDate('closes_at', '>=', now()->toDateString());
            })
            ->with('locations')
            ->orderBy('title')
            ->get()
            ->filter->hasAnyOpenVacancy()
            ->values();
PHP;

if (!str_contains($controller, $oldShowRegister)) {
    fail('showRegister() anchor not found in CandidateAuthController.php -- the method may have already been patched or has changed shape.');
}
$controller = str_replace($oldShowRegister, $newShowRegister, $controller);

// --- 1b. register(): drop the whole job_posting_location_id resolution
//         block (location lookup, isFilled check, "please select a
//         place" branch) and fall back to hasAnyOpenVacancy() for the
//         single already-open-posting-level filled check.
$oldRegisterBlock = <<<'PHP'
        // Resolve and verify the chosen location actually belongs to this
        // posting (never trust the submitted ID on its own -- a tampered
        // value could reference an unrelated posting's location). If the
        // posting HAS location rows, a place must be chosen; if it has
        // none (legacy posting), the field is skipped client-side and no
        // location is expected here.
        $jobPostingLocation = null;
        $postingHasLocations = $jobPosting->locations()->exists();

        if (!empty($validated['job_posting_location_id'])) {
            $jobPostingLocation = $jobPosting->locations()->find((int) $validated['job_posting_location_id']);
            if (!$jobPostingLocation) {
                return back()
                    ->withInput()
                    ->withErrors(['job_posting_location_id' => 'Sorry, that place of assignment is no longer available. Please choose another option.']);
            }
            if ($jobPostingLocation->isFilled()) {
                return back()
                    ->withInput()
                    ->withErrors(['job_posting_location_id' => 'Sorry, that place of assignment was just filled. Please choose another option.']);
            }
        } elseif ($postingHasLocations) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_location_id' => 'Please select a place of assignment.']);
        } elseif (!$jobPosting->hasOpenLegacyVacancy()) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, this position was just filled. Please choose another open position.']);
        }
PHP;

$newRegisterBlock = <<<'PHP'
        // Place of Assignment is no longer picked on the public form --
        // just re-check the posting hasn't been filled or closed between
        // page load and submit (hasAnyOpenVacancy() sums across every
        // location, or falls back to the legacy vacancies column).
        if (!$jobPosting->hasAnyOpenVacancy()) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, this position was just filled. Please choose another open position.']);
        }

        if ($jobPosting->closes_at && $jobPosting->closes_at->lt(now()->startOfDay())) {
            return back()
                ->withInput()
                ->withErrors(['job_posting_id' => 'Sorry, the application period for this position has closed. Please choose another open position.']);
        }
PHP;

if (!str_contains($controller, $oldRegisterBlock)) {
    fail('register() location-resolution anchor not found in CandidateAuthController.php -- the method may have already been patched or has changed shape.');
}
$controller = str_replace($oldRegisterBlock, $newRegisterBlock, $controller);

// --- 1c. position_applied string: drop the location suffix (variable no
//         longer exists), keep the legacy posting-level fallback.
$oldPositionApplied = <<<'PHP'
            'position_applied' => $jobPosting->title . ($jobPostingLocation ? ' - ' . $jobPostingLocation->place_of_assignment : ($jobPosting->place_of_assignment ? ' - ' . $jobPosting->place_of_assignment : '')),
PHP;

$newPositionApplied = <<<'PHP'
            'position_applied' => $jobPosting->title . ($jobPosting->place_of_assignment ? ' - ' . $jobPosting->place_of_assignment : ''),
PHP;

if (!str_contains($controller, $oldPositionApplied)) {
    fail('position_applied anchor not found in CandidateAuthController.php.');
}
$controller = str_replace($oldPositionApplied, $newPositionApplied, $controller);

// --- 1d. Application::create: drop the job_posting_location_id column
//         (variable no longer exists).
$oldAppCreateLine = <<<'PHP'
'job_posting_location_id' => $jobPostingLocation->id ?? null,
PHP;

if (!str_contains($controller, $oldAppCreateLine)) {
    fail('Application::create job_posting_location_id anchor not found in CandidateAuthController.php.');
}
$controller = str_replace($oldAppCreateLine . "\n", '', $controller);

if ($controller === $originalController) {
    fail('No changes were made to CandidateAuthController.php -- refusing to write an unchanged file.');
}

backup($controllerPath);
file_put_contents($controllerPath, $controller);
echo "  patched.\n\n";

// ─────────────────────────────────────────────────────────────────────────
// 2. resources/views/portal/register.blade.php
// ─────────────────────────────────────────────────────────────────────────
if (!file_exists($bladePath)) {
    fail("Blade view not found at {$bladePath}. Edit \$bladePath at the top of this script.");
}
$blade = file_get_contents($bladePath);
$originalBlade = $blade;

echo "Patching portal/register.blade.php...\n";

// --- 2a. Remove the "Place of Assignment" dependent field block.
$oldPlaceField = <<<'BLADE'
      {{-- 2b. Place of assignment — populated based on the position picked above --}}
      <div class="mb-4 d-none" id="placeFieldWrap">
        <label class="form-label">Place of Assignment <span class="required-star">*</span></label>
        @error('job_posting_location_id')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
        <select name="job_posting_location_id" id="placeSelect" class="form-select @error('job_posting_location_id') is-invalid @enderror">
          <option value="">— Select a place —</option>
        </select>
        <p class="hint" id="placeVacancyHint"></p>
      </div>

      @php
        // Every open posting's OPEN locations only (already-filled places
        // are excluded server-side by hasAnyOpenVacancy()/openLocations()),
        // keyed by posting id, with the REMAINING vacancy count -- so the
        // Place dropdown can populate instantly client-side without a page
        // reload or AJAX round-trip.
        $postingLocationsMap = $openPostings->mapWithKeys(function ($posting) {
            return [$posting->id => $posting->openLocations()->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'place' => $loc->place_of_assignment,
                    'vacancies' => $loc->remainingVacancies(),
                ];
            })->values()];
        });
      @endphp
      <script>
        window.__postingLocations = @json($postingLocationsMap);
BLADE;

if (!str_contains($blade, $oldPlaceField)) {
    fail('Place of Assignment field block anchor not found in register.blade.php -- the view may have already been patched or has changed shape.');
}
$blade = str_replace($oldPlaceField, '', $blade);

// --- 2b. Remove the JS block that populates/wires the Place select2.
//         This is a document.ready() call whose body only does Place-field
//         work plus the position select2 init -- keep the position select2
//         init, drop everything Place-related.
$oldJsBlock = <<<'JS'
<script>
  $(document).ready(function() {
      $('select[name="job_posting_id"]').select2({
          placeholder: '— Select a position —',
          allowClear: true,
      });
      $('#placeSelect').select2({
          placeholder: '— Select a place —',
          allowClear: true,
      });

      const postingLocations = window.__postingLocations || {};
      const oldLocationId = window.__oldJobPostingLocationId;
      const $positionSelect = $('#jobPostingSelect');
      const $placeWrap = $('#placeFieldWrap');
      const $placeSelect = $('#placeSelect');
      const $placeHint = $('#placeVacancyHint');

      function populatePlaces(postingId, preselectId) {
          const locations = postingLocations[postingId] || [];

          $placeSelect.empty().append('<option value="">— Select a place —</option>');

          if (locations.length === 0) {
              // This posting has no separate location rows (legacy single
              // place_of_assignment only) -- nothing to choose, hide the field
              // entirely and let job_posting_location_id submit as empty.
              $placeWrap.addClass('d-none');
              $placeSelect.prop('required', false);
              $placeHint.text('');
              $placeSelect.trigger('change.select2');
              return;
          }

          locations.forEach(function (loc) {
              const label = loc.place + ' (' + loc.vacancies + ' vacanc' + (loc.vacancies === 1 ? 'y' : 'ies') + ')';
              const opt = new Option(label, loc.id, false, String(loc.id) === String(preselectId));
              $placeSelect.append(opt);
          });

          $placeSelect.prop('required', true);
          $placeWrap.removeClass('d-none').addClass('place-field-in');
          $placeSelect.trigger('change.select2');
          updateVacancyHint();
      }

      function updateVacancyHint() {
          const selected = postingLocations[$positionSelect.val()] || [];
          const match = selected.find(function (loc) { return String(loc.id) === String($placeSelect.val()); });
          $placeHint.text(match ? match.vacancies + ' vacanc' + (match.vacancies === 1 ? 'y' : 'ies') + ' available at this location.' : '');
      }

      $positionSelect.on('change', function () {
          populatePlaces(this.value, null);
      });
      $placeSelect.on('change', updateVacancyHint);

      // Repopulate on load if a position (and possibly place) was already
      // selected -- e.g. validation error bounced the user back here.
      if ($positionSelect.val()) {
          populatePlaces($positionSelect.val(), oldLocationId);
      }
  });
</script>
JS;

$newJsBlock = <<<'JS'
<script>
  $(document).ready(function() {
      $('select[name="job_posting_id"]').select2({
          placeholder: '— Select a position —',
          allowClear: true,
      });
  });
</script>
JS;

if (!str_contains($blade, $oldJsBlock)) {
    fail('Place-field JS block anchor not found in register.blade.php -- the view may have already been patched or has changed shape.');
}
$blade = str_replace($oldJsBlock, $newJsBlock, $blade);

// --- 2c. Drop the now-dead #placeFieldWrap / #placeVacancyHint CSS.
$oldCss = <<<'CSS'

  /* Place of assignment — dependent field */
  #placeFieldWrap.place-field-in { animation: place-field-in .25s ease both; }
  @keyframes place-field-in {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
  }
  #placeVacancyHint { color:var(--teal-mid,#2b7a78); font-weight:500; }
CSS;

if (str_contains($blade, $oldCss)) {
    $blade = str_replace($oldCss, '', $blade);
} else {
    echo "  (note: dead CSS block for #placeFieldWrap not found -- skipped, not fatal)\n";
}

if ($blade === $originalBlade) {
    fail('No changes were made to register.blade.php -- refusing to write an unchanged file.');
}

backup($bladePath);
file_put_contents($bladePath, $blade);
echo "  patched.\n\n";

echo "Done. Review the diffs, then test the register page:\n";
echo "  - A posting with all locations hired should vanish from the dropdown.\n";
echo "  - A posting whose closes_at is in the past should vanish from the dropdown.\n";
echo "  - Submitting should no longer reference job_posting_location_id anywhere.\n";
