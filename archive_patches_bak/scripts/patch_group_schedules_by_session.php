<?php
/**
 * patch_group_schedules_by_session.php
 *
 * Reworks the Step 3 (Open Ranking & Scheduling) schedules table.
 *
 * BEFORE: one row per applicant. A single bulk "New schedule" action
 * (which schedules every applicant on the posting at the same date/time)
 * produced one visually-duplicated row per applicant -- same date, same
 * type, same panelists, repeated over and over.
 *
 * AFTER: one row per scheduling SESSION (grouped by date/time + venue,
 * since a single bulk action always shares those). Each row shows the
 * type(s), date & time, venue, panelists, and an "N applicant(s)" button
 * that opens a modal listing exactly who is in that session -- with all
 * the same per-applicant detail as before (name popover with full info,
 * per-type remove button, per-type status badge).
 *
 * Run once from the project root:
 *   php patch_group_schedules_by_session.php
 * Then delete this file — it is a one-shot installer, not idempotent.
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

$file = __DIR__ . '/resources/views/job-postings/show.blade.php';

$old = <<<'OLD'
                    @if ($schedules->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No schedules yet.</p>
                    @else
                    @php $groupedSchedules = $schedules->groupBy('application_id'); @endphp
                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Type</th>
                                <th class="text-nowrap">Date &amp; time</th>
                                <th>Panelists</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groupedSchedules as $appId => $group)
                            @php
                                $first = $group->first();
                                $sc = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary'];
                                $statuses = $group->pluck('status')->unique();
                            @endphp
                            <tr>
                                @php
                                    $schedCand = $first->application->candidate;
                                    $schedPlace = optional($first->application->jobPostingLocation)->place_of_assignment
                                        ?? $posting->place_of_assignment
                                        ?? null;
                                    $schedCheckData = $first->application->qualification_check ?? [];
                                    $schedCriteria = [];
                                    foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ck => $cl) {
                                        if (isset($schedCheckData['criteria'][$ck])) {
                                            $schedCriteria[] = [
                                                'label' => $cl,
                                                'actual' => $schedCheckData['criteria'][$ck]['actual'] ?? null,
                                                'passed' => (bool) ($schedCheckData['criteria'][$ck]['passed'] ?? false),
                                            ];
                                        }
                                    }
                                    $schedInfoData = [
                                        'name' => $schedCand->full_name,
                                        'email' => $schedCand->email,
                                        'phone' => $schedCand->phone,
                                        'address' => $schedCand->address,
                                        'age' => $schedCand->age,
                                        'sex' => $schedCand->sex,
                                        'civil_status' => $schedCand->civil_status,
                                        'religion' => $schedCand->religion,
                                        'disability' => $schedCand->disability,
                                        'ethnic_group' => $schedCand->ethnic_group,
                                        'education' => $schedCand->education,
                                        'training_hours' => $schedCand->training_hours,
                                        'years_experience' => $schedCand->years_experience,
                                        'eligibility' => $schedCand->eligibility,
                                        'transaction_number' => $first->application->transaction_number,
                                        'applied_at' => $first->application->applied_at ? \Carbon\Carbon::parse($first->application->applied_at)->format('M d, Y') : null,
                                        'status' => str_replace('_', ' ', ucfirst($first->application->status)),
                                        'place_of_assignment' => $schedPlace,
                                        'notes' => $first->application->notes,
                                        'qualification_result' => $first->application->qualification_result ? ucfirst(str_replace('_', ' ', $first->application->qualification_result)) : null,
                                        'criteria' => $schedCriteria,
                                    ];
                                @endphp
                                <td class="fw-medium">
                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                          title="View applicant information"
                                          onclick="showApplicantInfo(this)"
                                          data-info="{{ json_encode($schedInfoData) }}">
                                        {{ $schedCand->full_name }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($group as $s)
                                        <span class="badge text-bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size:0.75rem;">
                                            {{ str_replace('_',' ',ucfirst($s->type)) }}
                                            @if ($currentStep < 4)
                                            <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline m-0 p-0"
                                                  onsubmit="return confirm('Remove the {{ str_replace('_',' ',ucfirst($s->type)) }} schedule for {{ addslashes($first->application->candidate->full_name) }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-link btn-sm p-0 text-danger" style="line-height:1;" title="Remove">
                                                    <i class="bi bi-x-lg" style="font-size:0.65rem;"></i>
                                                </button>
                                            </form>
                                            @endif
                                        </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $first->scheduled_at ? \Carbon\Carbon::parse($first->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                                <td class="small">
                                    @if ($first->panelists->isNotEmpty())
                                        {{ $first->panelists->pluck('name')->implode(', ') }}
                                    @elseif ($first->interviewer_name)
                                        {{ $first->interviewer_name }}
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    @if ($statuses->count() === 1)
                                        <span class="badge text-bg-{{ $sc[$statuses->first()] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($statuses->first())) }}</span>
                                    @else
                                        @foreach ($statuses as $st)
                                            <span class="badge text-bg-{{ $sc[$st] ?? 'secondary' }} me-1">{{ str_replace('_',' ',ucfirst($st)) }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
OLD;

$new = <<<'NEW'
                    @if ($schedules->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No schedules yet.</p>
                    @else
                    @php
                        $sc = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary'];
                        // One "session" = everything created together in a single
                        // bulk "New schedule" action, which always shares the same
                        // date/time + venue across every applicant included.
                        $sessionGroups = $schedules->groupBy(function ($s) {
                            return $s->scheduled_at . '|' . $s->location;
                        })->values();
                    @endphp
                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th class="text-nowrap">Date &amp; time</th>
                                <th>Venue</th>
                                <th>Panelists</th>
                                <th>Applicants</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessionGroups as $sessIdx => $sessionSchedules)
                            @php
                                $sessFirst    = $sessionSchedules->first();
                                $sessTypes    = $sessionSchedules->pluck('type')->unique();
                                $sessAppCount = $sessionSchedules->pluck('application_id')->unique()->count();
                                $sessStatuses = $sessionSchedules->pluck('status')->unique();
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($sessTypes as $t)
                                        <span class="badge text-bg-light text-dark border" style="font-size:0.75rem;">{{ str_replace('_',' ',ucfirst($t)) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $sessFirst->scheduled_at ? \Carbon\Carbon::parse($sessFirst->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                                <td>{{ $sessFirst->location ?: '—' }}</td>
                                <td class="small">
                                    @if ($sessFirst->panelists->isNotEmpty())
                                        {{ $sessFirst->panelists->pluck('name')->implode(', ') }}
                                    @elseif ($sessFirst->interviewer_name)
                                        {{ $sessFirst->interviewer_name }}
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#sessionApplicantsModal{{ $sessIdx }}">
                                        <i class="bi bi-people me-1"></i> {{ $sessAppCount }} {{ Str::plural('applicant', $sessAppCount) }}
                                    </button>
                                </td>
                                <td>
                                    @if ($sessStatuses->count() === 1)
                                        <span class="badge text-bg-{{ $sc[$sessStatuses->first()] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($sessStatuses->first())) }}</span>
                                    @else
                                        @foreach ($sessStatuses as $st)
                                            <span class="badge text-bg-{{ $sc[$st] ?? 'secondary' }} me-1">{{ str_replace('_',' ',ucfirst($st)) }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- One modal per session, listing the individual applicants
                         it covers -- same per-applicant detail (info popover,
                         per-type remove, per-type status) that used to live
                         directly in the main table. --}}
                    @foreach ($sessionGroups as $sessIdx => $sessionSchedules)
                    @php $sessByApp = $sessionSchedules->groupBy('application_id'); @endphp
                    <div class="modal fade" id="sessionApplicantsModal{{ $sessIdx }}" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">Scheduled applicants</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                                        <thead>
                                            <tr>
                                                <th>Candidate</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($sessByApp as $appId => $group)
                                            @php
                                                $first = $group->first();
                                                $statuses = $group->pluck('status')->unique();
                                                $schedCand = $first->application->candidate;
                                                $schedPlace = optional($first->application->jobPostingLocation)->place_of_assignment
                                                    ?? $posting->place_of_assignment
                                                    ?? null;
                                                $schedCheckData = $first->application->qualification_check ?? [];
                                                $schedCriteria = [];
                                                foreach (['education' => 'Education', 'experience' => 'Experience', 'training' => 'Training', 'eligibility' => 'Eligibility'] as $ck => $cl) {
                                                    if (isset($schedCheckData['criteria'][$ck])) {
                                                        $schedCriteria[] = [
                                                            'label' => $cl,
                                                            'actual' => $schedCheckData['criteria'][$ck]['actual'] ?? null,
                                                            'passed' => (bool) ($schedCheckData['criteria'][$ck]['passed'] ?? false),
                                                        ];
                                                    }
                                                }
                                                $schedInfoData = [
                                                    'name' => $schedCand->full_name,
                                                    'email' => $schedCand->email,
                                                    'phone' => $schedCand->phone,
                                                    'address' => $schedCand->address,
                                                    'age' => $schedCand->age,
                                                    'sex' => $schedCand->sex,
                                                    'civil_status' => $schedCand->civil_status,
                                                    'religion' => $schedCand->religion,
                                                    'disability' => $schedCand->disability,
                                                    'ethnic_group' => $schedCand->ethnic_group,
                                                    'education' => $schedCand->education,
                                                    'training_hours' => $schedCand->training_hours,
                                                    'years_experience' => $schedCand->years_experience,
                                                    'eligibility' => $schedCand->eligibility,
                                                    'transaction_number' => $first->application->transaction_number,
                                                    'applied_at' => $first->application->applied_at ? \Carbon\Carbon::parse($first->application->applied_at)->format('M d, Y') : null,
                                                    'status' => str_replace('_', ' ', ucfirst($first->application->status)),
                                                    'place_of_assignment' => $schedPlace,
                                                    'notes' => $first->application->notes,
                                                    'qualification_result' => $first->application->qualification_result ? ucfirst(str_replace('_', ' ', $first->application->qualification_result)) : null,
                                                    'criteria' => $schedCriteria,
                                                ];
                                            @endphp
                                            <tr>
                                                <td class="fw-medium">
                                                    <span role="button" style="border-bottom: 1px dashed #adb5bd;"
                                                          title="View applicant information"
                                                          onclick="showApplicantInfo(this)"
                                                          data-info="{{ json_encode($schedInfoData) }}">
                                                        {{ $schedCand->full_name }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach ($group as $s)
                                                        <span class="badge text-bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size:0.75rem;">
                                                            {{ str_replace('_',' ',ucfirst($s->type)) }}
                                                            @if ($currentStep < 4)
                                                            <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline m-0 p-0"
                                                                  onsubmit="return confirm('Remove the {{ str_replace('_',' ',ucfirst($s->type)) }} schedule for {{ addslashes($first->application->candidate->full_name) }}?')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="btn btn-link btn-sm p-0 text-danger" style="line-height:1;" title="Remove">
                                                                    <i class="bi bi-x-lg" style="font-size:0.65rem;"></i>
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($statuses->count() === 1)
                                                        <span class="badge text-bg-{{ $sc[$statuses->first()] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($statuses->first())) }}</span>
                                                    @else
                                                        @foreach ($statuses as $st)
                                                            <span class="badge text-bg-{{ $sc[$st] ?? 'secondary' }} me-1">{{ str_replace('_',' ',ucfirst($st)) }}</span>
                                                        @endforeach
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
NEW;

apply_patch($file, $old, $new, 'Step 3 schedules table: group by session instead of by applicant, with a per-session applicants modal');

echo "\nDone. The schedules table now shows one row per scheduling session (date/time +\n";
echo "venue) instead of one row per applicant. Click the applicant count button on a row\n";
echo "to see/manage who's included in that session.\n";
