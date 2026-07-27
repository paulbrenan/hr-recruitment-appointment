<?php
/**
 * One-shot patch: adds a date filter to the Activity Log Book.
 * Run from project root: php patch_activity_log_date_filter.php
 * Backs up both target files before modifying. Verifies exact expected
 * content before patching (aborts safely on any mismatch). Deletes
 * itself after a fully successful run.
 */

$root = __DIR__;
$controllerPath = $root . '/app/Http/Controllers/ActivityLogController.php';
$bladePath = $root . '/resources/views/layouts/app.blade.php';

function fail($msg) {
    echo "ABORTED: $msg\n";
    echo "No files were modified.\n";
    exit(1);
}

function backup($path) {
    $backupPath = $path . '.bak.' . date('Ymd_His');
    if (!copy($path, $backupPath)) {
        fail("Could not create backup for $path");
    }
    echo "Backed up: $backupPath\n";
    return $backupPath;
}

// ---------- Verify files exist ----------
if (!file_exists($controllerPath)) fail("Controller not found at $controllerPath");
if (!file_exists($bladePath)) fail("Blade layout not found at $bladePath");

$controllerContent = file_get_contents($controllerPath);
$bladeContent = file_get_contents($bladePath);

// ---------- Expected exact current controller content ----------
$expectedController = <<<'EOT'
<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user->name ?? 'System',
                    'action' => $log->action,
                    'subject_label' => $log->subject_label,
                    'description' => $log->description,
                    'created_at' => $log->created_at->format('M d, Y g:i A'),
                ];
            });

        return response()->json(['logs' => $logs]);
    }
}
EOT;

if (trim($controllerContent) !== trim($expectedController)) {
    fail("ActivityLogController.php content does not match expected content (file may have changed). Please re-paste current content.");
}

// ---------- New controller content ----------
$newController = <<<'EOT'
<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        } else {
            $query->limit(100);
        }

        $logs = $query->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user->name ?? 'System',
                'action' => $log->action,
                'subject_label' => $log->subject_label,
                'description' => $log->description,
                'created_at' => $log->created_at->format('M d, Y g:i A'),
            ];
        });

        return response()->json(['logs' => $logs]);
    }
}
EOT;

// ---------- Expected exact substrings in blade file ----------
$oldModalBody = <<<'EOT'
                <div class="modal-body">
                    <div id="activityLogLoading" class="text-center text-muted py-4">
EOT;

$newModalBody = <<<'EOT'
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label for="activityLogDateFilter" class="form-label small text-muted mb-0">Filter by date:</label>
                        <input type="date" id="activityLogDateFilter" class="form-control form-control-sm" style="max-width: 180px;">
                        <button type="button" id="activityLogDateClear" class="btn btn-sm btn-outline-secondary d-none">Clear</button>
                    </div>
                    <div id="activityLogLoading" class="text-center text-muted py-4">
EOT;

$oldScript = <<<'EOT'
        (function () {
            const modal = document.getElementById('activityLogModal');
            const loading = document.getElementById('activityLogLoading');
            const tableWrap = document.getElementById('activityLogTableWrap');
            const tableBody = document.getElementById('activityLogTableBody');
            const emptyState = document.getElementById('activityLogEmpty');
            const errorState = document.getElementById('activityLogError');
            function actionBadge(action) {
                const map = { created: 'success', updated: 'primary', deleted: 'danger' };
                const cls = map[action] || 'secondary';
                return '<span class="badge bg-' + cls + '-subtle text-' + cls + ' border border-' + cls + '-subtle text-capitalize">' + action + '</span>';
            }
            function render(logs) {
                loading.classList.add('d-none');
                if (!logs.length) {
                    emptyState.classList.remove('d-none');
                    return;
                }
                tableBody.innerHTML = logs.map(function (log) {
                    return '<tr>' +
                        '<td class="text-nowrap small">' + log.created_at + '</td>' +
                        '<td class="small">' + log.user + '</td>' +
                        '<td>' + actionBadge(log.action) + '</td>' +
                        '<td class="small">' + (log.subject_label || log.description || '') + '</td>' +
                        '</tr>';
                }).join('');
                tableWrap.classList.remove('d-none');
            }
            modal.addEventListener('show.bs.modal', function () {
                loading.classList.remove('d-none');
                tableWrap.classList.add('d-none');
                emptyState.classList.add('d-none');
                errorState.classList.add('d-none');
                tableBody.innerHTML = '';
                fetch('{{ route("activity-logs.index") }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('bad response');
                        return res.json();
                    })
                    .then(function (data) {
                        render(data.logs || []);
                    })
                    .catch(function () {
                        loading.classList.add('d-none');
                        errorState.classList.remove('d-none');
                    });
            });
        })();
EOT;

$newScript = <<<'EOT'
        (function () {
            const modal = document.getElementById('activityLogModal');
            const loading = document.getElementById('activityLogLoading');
            const tableWrap = document.getElementById('activityLogTableWrap');
            const tableBody = document.getElementById('activityLogTableBody');
            const emptyState = document.getElementById('activityLogEmpty');
            const errorState = document.getElementById('activityLogError');
            const dateFilter = document.getElementById('activityLogDateFilter');
            const dateClear = document.getElementById('activityLogDateClear');
            function actionBadge(action) {
                const map = { created: 'success', updated: 'primary', deleted: 'danger' };
                const cls = map[action] || 'secondary';
                return '<span class="badge bg-' + cls + '-subtle text-' + cls + ' border border-' + cls + '-subtle text-capitalize">' + action + '</span>';
            }
            function render(logs) {
                loading.classList.add('d-none');
                if (!logs.length) {
                    emptyState.classList.remove('d-none');
                    return;
                }
                tableBody.innerHTML = logs.map(function (log) {
                    return '<tr>' +
                        '<td class="text-nowrap small">' + log.created_at + '</td>' +
                        '<td class="small">' + log.user + '</td>' +
                        '<td>' + actionBadge(log.action) + '</td>' +
                        '<td class="small">' + (log.subject_label || log.description || '') + '</td>' +
                        '</tr>';
                }).join('');
                tableWrap.classList.remove('d-none');
            }
            function loadLogs(date) {
                loading.classList.remove('d-none');
                tableWrap.classList.add('d-none');
                emptyState.classList.add('d-none');
                errorState.classList.add('d-none');
                tableBody.innerHTML = '';
                var url = '{{ route("activity-logs.index") }}';
                if (date) {
                    url += '?date=' + encodeURIComponent(date);
                }
                dateClear.classList.toggle('d-none', !date);
                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('bad response');
                        return res.json();
                    })
                    .then(function (data) {
                        render(data.logs || []);
                    })
                    .catch(function () {
                        loading.classList.add('d-none');
                        errorState.classList.remove('d-none');
                    });
            }
            modal.addEventListener('show.bs.modal', function () {
                dateFilter.value = '';
                loadLogs(null);
            });
            dateFilter.addEventListener('change', function () {
                loadLogs(dateFilter.value || null);
            });
            dateClear.addEventListener('click', function () {
                dateFilter.value = '';
                loadLogs(null);
            });
        })();
EOT;

// ---------- Verify blade substrings appear exactly once each ----------
$countModalBody = substr_count($bladeContent, $oldModalBody);
$countScript = substr_count($bladeContent, $oldScript);

if ($countModalBody !== 1) {
    fail("Expected modal-body block not found exactly once in app.blade.php (found $countModalBody). File may have drifted from expected content.");
}
if ($countScript !== 1) {
    fail("Expected activity log script block not found exactly once in app.blade.php (found $countScript). File may have drifted from expected content.");
}

// ---------- All verifications passed. Backup, then write. ----------
backup($controllerPath);
backup($bladePath);

if (file_put_contents($controllerPath, $newController) === false) {
    fail("Failed to write new controller content.");
}
echo "Patched: $controllerPath\n";

$newBladeContent = str_replace($oldModalBody, $newModalBody, $bladeContent);
$newBladeContent = str_replace($oldScript, $newScript, $newBladeContent);

if (file_put_contents($bladePath, $newBladeContent) === false) {
    fail("Failed to write new blade content. Controller was already patched -- restore controller from backup if needed.");
}
echo "Patched: $bladePath\n";

echo "\nSuccess. Date filter added to Activity Log Book.\n";
echo "Test with: php artisan serve, then open the Activity Log Book modal.\n";

// ---------- Self-delete ----------
@unlink(__FILE__);
echo "This patch script has removed itself.\n";
