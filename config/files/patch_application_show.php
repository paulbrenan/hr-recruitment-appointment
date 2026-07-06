<?php
/**
 * patch_application_show.php
 *
 * Redesigns the application show page:
 *  - Removes the avatar icon above the name
 *  - Removes the Documents card
 *  - Adds an Application Details card at the top of the right column
 *    (transaction number, applied date, position, place of assignment)
 *  - Moves Application Status to the bottom
 *  - Wires $documents and $schedules to real DB data in the controller
 *
 * Drop in project root, run once: php patch_application_show.php
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

// ── 1. Patch the view ─────────────────────────────────────────────────────────
echo "\n[1/2] Patching applications/show.blade.php...\n";

$viewPath = __DIR__ . '/resources/views/applications/show.blade.php';
if (!file_exists($viewPath)) { die("ERROR: Cannot find applications/show.blade.php\n"); }
do_backup($viewPath);

$newView = <<<'BLADE'
@extends('layouts.app')

@section('title', 'Application details')
@section('page-title', 'Application details')

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3">

    {{-- ── Left column: candidate card ──────────────────────────────────── --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-4 text-center">
                <h6 class="mb-0">{{ $application->candidate->full_name }}</h6>
                <p class="text-muted small mb-1">{{ $application->candidate->email }}</p>
                @if($application->candidate->phone)
                    <p class="text-muted small mb-3">
                        <i class="bi bi-telephone me-1"></i>{{ $application->candidate->phone }}
                    </p>
                @endif
                <hr>
                <p class="small text-muted mb-1">Applying for</p>
                <p class="fw-medium mb-0">{{ $application->jobPosting->title ?? '—' }}</p>

                @if($application->status === 'rejected')
                <hr>
                @if($application->talentPool)
                    <span class="badge bg-success">Already in Talent Pool</span>
                @else
                    <form action="{{ route('talent-pool.store-from-application', $application->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-bookmark-plus me-1"></i> Add to Talent Pool
                        </button>
                    </form>
                @endif
                @endif

                <hr>
                <form action="{{ route('applications.destroy', $application->id) }}" method="POST"
                      onsubmit="return confirm('Delete this application? This will also delete any linked documents, interview schedules, assessments, job offers, and appointments. This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash me-1"></i> Delete application
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Right column: details, schedule, status ──────────────────────── --}}
    <div class="col-md-8">

        {{-- Application details --}}
        <div class="card mb-3">
            <div class="card-body p-4">
                <h6 class="mb-3">Application details</h6>
                <div class="row g-2 small">
                    @if($application->transaction_number)
                    <div class="col-6">
                        <div class="text-muted mb-1">Transaction No.</div>
                        <div class="fw-medium font-monospace">{{ $application->transaction_number }}</div>
                    </div>
                    @endif
                    <div class="col-6">
                        <div class="text-muted mb-1">Date Applied</div>
                        <div class="fw-medium">
                            {{ $application->applied_at
                                ? \Carbon\Carbon::parse($application->applied_at)->format('M d, Y')
                                : '—' }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted mb-1">Position</div>
                        <div class="fw-medium">{{ $application->jobPosting->title ?? '—' }}</div>
                    </div>
                    @if(!empty($application->jobPosting->salary_grade))
                    <div class="col-6">
                        <div class="text-muted mb-1">Salary Grade</div>
                        <div class="fw-medium">SG-{{ $application->jobPosting->salary_grade }}</div>
                    </div>
                    @endif
                    @if(!empty($application->jobPosting->place_of_assignment))
                    <div class="col-12">
                        <div class="text-muted mb-1">Place of Assignment</div>
                        <div class="fw-medium">{{ $application->jobPosting->place_of_assignment }}</div>
                    </div>
                    @endif
                    @if(!empty($application->jobPosting->employment_type))
                    <div class="col-6">
                        <div class="text-muted mb-1">Employment Type</div>
                        <div class="fw-medium">{{ $application->jobPosting->employment_type }}</div>
                    </div>
                    @endif
                    <div class="col-6">
                        <div class="text-muted mb-1">Current Status</div>
                        <div>
                            @php
                                $statusColors = [
                                    'submitted'           => 'secondary',
                                    'screening'           => 'info',
                                    'shortlisted'         => 'primary',
                                    'interview_scheduled' => 'primary',
                                    'assessed'            => 'primary',
                                    'ranked'              => 'primary',
                                    'offer_sent'          => 'warning',
                                    'offer_accepted'      => 'success',
                                    'offer_declined'      => 'danger',
                                    'hired'               => 'success',
                                    'rejected'            => 'danger',
                                ];
                                $color = $statusColors[$application->status] ?? 'secondary';
                            @endphp
                            <span class="badge text-bg-{{ $color }}">
                                {{ str_replace('_', ' ', ucfirst($application->status)) }}
                            </span>
                        </div>
                    </div>
                    @if($application->notes)
                    <div class="col-12">
                        <div class="text-muted mb-1">Notes</div>
                        <div class="fw-medium">{{ $application->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Interview / exam schedule --}}
        <div class="card mb-3">
            <div class="card-body p-4">
                <h6 class="mb-3">Interview / exam schedule</h6>
                @forelse ($schedules as $s)
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <div class="fw-medium small">{{ str_replace('_', ' ', ucfirst($s->type)) }}</div>
                        <div class="text-muted small">
                            {{ \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') }}
                            @if($s->location) &middot; {{ $s->location }} @endif
                        </div>
                    </div>
                    <span class="badge text-bg-secondary">{{ ucfirst($s->status) }}</span>
                </div>
                @empty
                <p class="text-muted small mb-2">No schedule set yet.</p>
                @endforelse
                <button class="btn btn-sm btn-outline-secondary mt-3">
                    <i class="bi bi-plus-lg me-1"></i> Schedule interview/exam
                </button>
            </div>
        </div>

        {{-- Application status (update form) --}}
        <div class="card">
            <div class="card-body p-4">
                <h6 class="mb-3">Update application status</h6>
                @if ($errors->any())
                    <div class="alert alert-danger small">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('applications.updateStatus', $application->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <select name="status" class="form-select">
                        @foreach (['submitted', 'screening', 'shortlisted', 'interview_scheduled', 'assessed', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as $status)
                            <option value="{{ $status }}" {{ old('status', $application->status) === $status ? 'selected' : '' }}>
                                {{ str_replace('_', ' ', ucfirst($status)) }}
                            </option>
                        @endforeach
                    </select>
                    <textarea name="notes" class="form-control mt-2" rows="2"
                              placeholder="Add notes about this application...">{{ old('notes', $application->notes) }}</textarea>
                    <button type="submit" class="btn btn-sm mt-2"
                            style="background-color: var(--hr-primary); color: #fff;">
                        Update status
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $newView);
echo "  Written: resources/views/applications/show.blade.php\n";

// ── 2. Patch the controller: wire real $schedules, remove dummy $documents ────
echo "\n[2/2] Patching ApplicationController.php...\n";

$ctrlPath = __DIR__ . '/app/Http/Controllers/ApplicationController.php';
if (!file_exists($ctrlPath)) { die("ERROR: Cannot find ApplicationController.php\n"); }
do_backup($ctrlPath);

$ctrl = file_get_contents($ctrlPath);

apply_patch($ctrl,
    'use App\Models\Application;
use Illuminate\Http\Request;',
    'use App\Models\Application;
use App\Models\InterviewSchedule;
use Illuminate\Http\Request;',
    'add InterviewSchedule use statement'
);

apply_patch($ctrl,
    '    public function show($id)
    {
        $application = Application::with([\'candidate\', \'jobPosting\'])->findOrFail($id);

        // NOTE: Application Documents and Interview Scheduling modules are
        // not wired to real data yet. These dummy collections are left in
        // place intentionally and should be replaced once those modules\'
        // controllers/migrations are wired up (application_documents and
        // interview_schedules tables already exist).
        $documents = collect([
            (object) [\'document_type\' => \'Resume\'],
            (object) [\'document_type\' => \'Transcript of Records\'],
            (object) [\'document_type\' => \'Valid ID\'],
        ]);

        $schedules = collect([
            (object) [\'type\' => \'interview\', \'scheduled_at\' => \'2026-06-20 10:00:00\', \'location\' => \'HR Conference Room\', \'status\' => \'scheduled\'],
        ]);

        return view(\'applications.show\', compact(\'application\', \'documents\', \'schedules\'));
    }',
    '    public function show($id)
    {
        $application = Application::with([\'candidate\', \'jobPosting\'])->findOrFail($id);

        // Real interview schedules from the database
        $schedules = InterviewSchedule::where(\'application_id\', $id)
            ->orderBy(\'scheduled_at\')
            ->get();

        return view(\'applications.show\', compact(\'application\', \'schedules\'));
    }',
    'wire real $schedules, remove dummy $documents'
);

file_put_contents($ctrlPath, $ctrl);
echo "  Patched: app/Http/Controllers/ApplicationController.php\n";

echo "\n✓ Done. No migration needed.\n";
echo "  - Avatar icon removed from candidate card\n";
echo "  - Documents card removed\n";
echo "  - Application Details card added at top of right column\n";
echo "    (transaction number, date applied, position, SG, place, employment type, status badge, notes)\n";
echo "  - Application Status update form moved to bottom\n";
echo "  - Interview schedules now pull from real DB (not dummy data)\n";
echo "Delete this script when confirmed working.\n";
