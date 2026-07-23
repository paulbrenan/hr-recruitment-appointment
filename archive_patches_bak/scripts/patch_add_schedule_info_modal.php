<?php
/**
 * patch_add_schedule_info_modal.php
 *
 * Makes the Type badges in the Step 3 schedules table (session rows,
 * added by patch_group_schedules_by_session.php) clickable, opening a
 * shared modal with schedule info -- same interaction pattern as
 * clicking an applicant's name (dashed-underline trigger, data-info
 * JSON, shared modal populated by JS).
 *
 * Shows information not already visible in the row:
 *   - Per-type status AND remarks (the `remarks` column already exists
 *     on interview_schedules but was never surfaced anywhere in the UI)
 *   - Panelist email addresses (Panelist already has an email column,
 *     the row only ever showed names)
 *
 * Run once from the project root:
 *   php patch_add_schedule_info_modal.php
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

// ── 1. Make the Type badges clickable, carrying a data-info payload ─────

$old1 = <<<'OLD'
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($sessTypes as $t)
                                        <span class="badge text-bg-light text-dark border" style="font-size:0.75rem;">{{ str_replace('_',' ',ucfirst($t)) }}</span>
                                        @endforeach
                                    </div>
                                </td>
OLD;

$new1 = <<<'NEW'
                                <td>
                                    @php
                                        $sessInfoData = [
                                            'scheduled_at' => $sessFirst->scheduled_at ? \Carbon\Carbon::parse($sessFirst->scheduled_at)->format('M d, Y h:i A') : null,
                                            'location' => $sessFirst->location,
                                            'applicant_count' => $sessAppCount,
                                            'panelists' => $sessFirst->panelists->map(fn ($p) => ['name' => $p->name, 'email' => $p->email])->values(),
                                            'types' => $sessTypes->map(function ($t) use ($sessionSchedules) {
                                                $typeSchedules = $sessionSchedules->where('type', $t);
                                                $statuses = $typeSchedules->pluck('status')->unique()->map(fn ($s) => str_replace('_', ' ', ucfirst($s)))->implode(', ');
                                                $remarks = $typeSchedules->pluck('remarks')->filter()->unique()->implode(' | ');
                                                return [
                                                    'type' => str_replace('_', ' ', ucfirst($t)),
                                                    'status' => $statuses,
                                                    'remarks' => $remarks ?: null,
                                                ];
                                            })->values(),
                                        ];
                                    @endphp
                                    <div class="d-flex flex-wrap gap-1" role="button" title="View schedule details"
                                         onclick="showScheduleInfo(this)" data-info="{{ json_encode($sessInfoData) }}">
                                        @foreach ($sessTypes as $t)
                                        <span class="badge text-bg-light text-dark border" style="font-size:0.75rem;">{{ str_replace('_',' ',ucfirst($t)) }}</span>
                                        @endforeach
                                    </div>
                                </td>
NEW;

apply_patch($file, $old1, $new1, 'Session row: make Type badges clickable, carrying schedule info as JSON');

// ── 2. Shared modal + JS, inserted right after the per-session applicant
//       modals loop ends ────────────────────────────────────────────────

$old2 = <<<'OLD'
                    </div>
                    @endforeach
                    @endif
                </div>
OLD;

$new2 = <<<'NEW'
                    </div>
                    @endforeach
                    @endif

                    <div class="modal fade" id="scheduleInfoModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title">Schedule details</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <div class="text-muted small">Date &amp; time</div>
                                        <div class="fw-medium" id="si-scheduled-at">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Venue</div>
                                        <div class="fw-medium" id="si-location">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small">Applicants</div>
                                        <div class="fw-medium" id="si-applicant-count">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-muted small mb-1">Type breakdown</div>
                                        <table class="table table-sm mb-0" style="font-size:0.85rem;">
                                            <thead>
                                                <tr><th>Type</th><th>Status</th><th>Remarks</th></tr>
                                            </thead>
                                            <tbody id="si-types-body"></tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Panelists</div>
                                        <ul class="mb-0 ps-3" id="si-panelists-list" style="font-size:0.85rem;"></ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
NEW;

apply_patch($file, $old2, $new2, 'Insert #scheduleInfoModal after the per-session applicant modals');

// ── 3. showScheduleInfo() JS ─────────────────────────────────────────────

$old3 = <<<'OLD'
function advanceStep() {
OLD;

$new3 = <<<'NEW'
// ── Schedule info modal (triggered by clicking a session's Type badges) ─
function showScheduleInfo(el) {
    const data = JSON.parse(el.getAttribute('data-info'));

    document.getElementById('si-scheduled-at').textContent = data.scheduled_at || '—';
    document.getElementById('si-location').textContent = data.location || '—';
    document.getElementById('si-applicant-count').textContent = data.applicant_count + (data.applicant_count === 1 ? ' applicant' : ' applicants');

    const typesBody = document.getElementById('si-types-body');
    typesBody.innerHTML = '';
    (data.types || []).forEach(function (t) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + t.type + '</td><td>' + (t.status || '—') + '</td><td>' + (t.remarks || '—') + '</td>';
        typesBody.appendChild(tr);
    });

    const panelistsList = document.getElementById('si-panelists-list');
    panelistsList.innerHTML = '';
    if (!data.panelists || data.panelists.length === 0) {
        panelistsList.innerHTML = '<li class="text-muted">No panelists assigned</li>';
    } else {
        data.panelists.forEach(function (p) {
            const li = document.createElement('li');
            li.textContent = p.name + (p.email ? ' — ' + p.email : '');
            panelistsList.appendChild(li);
        });
    }

    new bootstrap.Modal(document.getElementById('scheduleInfoModal')).show();
}

function advanceStep() {
NEW;

apply_patch($file, $old3, $new3, 'Add showScheduleInfo() JS to populate and open #scheduleInfoModal');

echo "\nDone. Clicking a session's Type badges in Step 3 now opens a modal with\n";
echo "date/venue/applicant count, per-type status + remarks, and panelist emails.\n";
