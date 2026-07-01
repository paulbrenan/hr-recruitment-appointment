<?php
/**
 * patch_application_show_candidate.php
 *
 * Adds a full candidate details section to the application show page,
 * displaying all fields submitted via the online recruitment form:
 * personal info (age, sex, civil status, religion, disability, ethnic group,
 * address) and qualifications (education, training hours, years experience,
 * eligibility).
 *
 * Drop in project root, run once: php patch_application_show_candidate.php
 * No migration needed. Delete after confirming it works.
 */

function do_backup(string $path): void {
    $bak = $path . '.bak';
    $i   = 2;
    while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
    file_put_contents($bak, file_get_contents($path));
    echo "  Backed up: $bak\n";
}

function apply_patch(string &$src, string $find, string $replace, string $label): void {
    $count = substr_count($src, $find);
    if ($count === 0) { die("ERROR [$label]: Target string not found — aborting, nothing written.\n"); }
    if ($count  > 1) { die("ERROR [$label]: Found $count matches (expected 1) — aborting.\n"); }
    $src = str_replace($find, $replace, $src);
    echo "  OK [$label]\n";
}

$viewPath = __DIR__ . '/resources/views/applications/show.blade.php';
if (!file_exists($viewPath)) { die("ERROR: Cannot find applications/show.blade.php\n"); }
do_backup($viewPath);

$view = file_get_contents($viewPath);

// Insert candidate details + qualifications cards after the application
// details card and before the interview schedule card.
apply_patch(
    $view,
    '        {{-- Interview / exam schedule --}}',
    '        {{-- Candidate personal information --}}
        <div class="card mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Personal Information</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Full Name</div>
                        <div class="fw-medium">{{ $application->candidate->full_name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Email</div>
                        <div class="fw-medium">{{ $application->candidate->email }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Contact No.</div>
                        <div class="fw-medium">{{ $application->candidate->phone ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Age</div>
                        <div class="fw-medium">{{ $application->candidate->age ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Sex</div>
                        <div class="fw-medium">{{ $application->candidate->sex ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Civil Status</div>
                        <div class="fw-medium">{{ $application->candidate->civil_status ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Religion</div>
                        <div class="fw-medium">{{ $application->candidate->religion ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Disability</div>
                        <div class="fw-medium">{{ $application->candidate->disability ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Ethnic Group</div>
                        <div class="fw-medium">{{ $application->candidate->ethnic_group ?? \'—\' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-1">Address</div>
                        <div class="fw-medium">{{ $application->candidate->address ?? \'—\' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Candidate qualifications --}}
        <div class="card mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-medium small">Qualifications</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 small">
                    <div class="col-12">
                        <div class="text-muted mb-1">Highest Educational Attainment</div>
                        <div class="fw-medium">{{ $application->candidate->education ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Training Hours</div>
                        <div class="fw-medium">{{ $application->candidate->training_hours ?? \'—\' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted mb-1">Years of Experience</div>
                        <div class="fw-medium">{{ $application->candidate->years_experience ?? \'—\' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-1">Eligibility</div>
                        <div class="fw-medium">{{ $application->candidate->eligibility ?? \'—\' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Interview / exam schedule --}}',
    'insert personal info + qualifications cards before schedule'
);

file_put_contents($viewPath, $view);
echo "  Patched: resources/views/applications/show.blade.php\n";

echo "\n✓ Done. No migration needed.\n";
echo "  Open any application — you should now see:\n";
echo "  1. Application Details (transaction no, date, position, SG, place, status)\n";
echo "  2. Personal Information (name, email, phone, age, sex, civil status, religion, disability, ethnic group, address)\n";
echo "  3. Qualifications (education, training hours, years experience, eligibility)\n";
echo "  4. Interview / exam schedule\n";
echo "  5. Update application status\n";
echo "Delete this script when confirmed working.\n";
