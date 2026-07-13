<?php
/**
 * patch_group_schedules_by_candidate.php
 *
 * Run from the project root:
 *   php patch_group_schedules_by_candidate.php
 *
 * What it does:
 *  resources/views/job-postings/show.blade.php — Step 3 "Interview / exam
 *  schedules" table: instead of one row per schedule (so a candidate with
 *  Open ranking + Interview + Exam took 3 rows), groups by candidate and
 *  shows all their type badges in a single row. Each badge keeps its own
 *  small "x" to remove just that one type, since they're still separate
 *  InterviewSchedule records under the hood.
 *
 * Safe to run multiple times: aborts with no changes if the expected block
 * isn't found exactly (e.g. already patched, or file has changed since).
 * A .bak copy is made before any write.
 */

$root = __DIR__;
$path = $root . '/resources/views/job-postings/show.blade.php';

if (!file_exists($path)) {
    echo "[SKIP] show.blade.php — file not found: $path\n";
    exit;
}

$content = file_get_contents($path);
$original = $content;

$old = <<<'OLD'
                    @if ($schedules->isEmpty())
                        <p class="text-muted small mb-0 text-center py-3">No schedules yet.</p>
                    @else
                    <table class="table align-middle mb-0" style="font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Type</th>
                                <th class="text-nowrap">Date &amp; time</th>
                                <th>Panelists</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $s)
                            @php $sc = ['scheduled'=>'primary','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary']; @endphp
                            <tr>
                                <td class="fw-medium">{{ $s->application->candidate->full_name }}</td>
                                <td><span class="badge text-bg-light text-dark border">{{ str_replace('_',' ',ucfirst($s->type)) }}</span></td>
                                <td>{{ $s->scheduled_at ? \Carbon\Carbon::parse($s->scheduled_at)->format('M d, Y h:i A') : '—' }}</td>
                                <td class="small">
                                    @if ($s->panelists->isNotEmpty())
                                        {{ $s->panelists->pluck('name')->implode(', ') }}
                                    @elseif ($s->interviewer_name)
                                        {{ $s->interviewer_name }}
                                    @else —
                                    @endif
                                </td>
                                <td><span class="badge text-bg-{{ $sc[$s->status] ?? 'secondary' }}">{{ str_replace('_',' ',ucfirst($s->status)) }}</span></td>
                                <td class="text-end">
                                    @if ($currentStep < 4)
                                    <form action="{{ route('interviews.destroy', $s->id) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this schedule?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
OLD;

$new = <<<'NEW'
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
                                <td class="fw-medium">{{ $first->application->candidate->full_name }}</td>
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
NEW;

if (strpos($content, $old) === false) {
    echo "[ABORT] show.blade.php — expected schedules table block not found (file may have changed since the last patch). No changes written.\n";
    exit;
}

$content = str_replace($old, $new, $content);

if ($content === $original) {
    echo "[SKIP] show.blade.php — no changes needed.\n";
    exit;
}

$backup = $path . '.bak';
if (!file_exists($backup)) {
    copy($path, $backup);
} else {
    copy($path, $path . '.bak.' . date('Ymd_His'));
}

file_put_contents($path, $content);
echo "[OK] show.blade.php — patched. Backup at: $backup\n";
