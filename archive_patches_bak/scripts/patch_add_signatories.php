<?php
/**
 * patch_add_signatories.php
 *
 * Adds a generic "Signatories" management page so document/email
 * templates (IER export, qualification notice, offer letter, etc.) stop
 * hardcoding names/titles and instead look up a configured signatory by
 * a fixed `key`.
 *
 * Schema: signatories(id, key [unique, e.g. 'ier_certifier'], label
 * [human-readable, e.g. "IER Certifying Officer"], name, position,
 * timestamps). Deliberately NOT a fixed set of roles -- you said the
 * roles/people/titles vary and aren't fully known yet, so this lets HR
 * add as many as needed rather than being locked into a rigid schema.
 * Open to any HR staff (no extra permission gate) per your answer.
 *
 * Wires this into the IER export (exportIER(), added by
 * patch_add_export_ier.php) as the first concrete usage: looks up a
 * signatory with key = 'ier_certifier'. If none is configured yet, falls
 * back to the EXISTING hardcoded behavior (logged-in user's name +
 * "Human Resource Management Officer") so nothing breaks before you've
 * set one up.
 *
 * Other templates (qualification notice email, offer letter, etc.) are
 * NOT wired in by this patch -- I don't have those files yet. The admin
 * page is ready regardless; send those templates over as a follow-up
 * and I'll wire each one the same way.
 *
 * Run once from the project root:
 *   php patch_add_signatories.php
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

function create_new_file($path, $contents, $label) {
    if (file_exists($path)) {
        fwrite(STDERR, "[SKIP] $label -- file already exists at $path, leaving it alone.\n");
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
    echo "[OK] $label\n";
}

$root = __DIR__;

// ── 1. Migration ──────────────────────────────────────────────────────

$migrationTimestamp = date('Y_m_d_His');
$migrationPath = "$root/database/migrations/{$migrationTimestamp}_create_signatories_table.php";

create_new_file($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            // Fixed slug that document/email templates look up by, e.g.
            // 'ier_certifier', 'qualification_notice_signatory',
            // 'offer_letter_signatory'. Templates reference this, NOT
            // the human-readable label below, so the label can be
            // reworded freely without breaking anything.
            $table->string('key')->unique();
            // Human-readable description shown in the admin UI, e.g.
            // "IER Certifying Officer" or "HRMPSB Chairperson".
            $table->string('label');
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};
PHP,
    'Create migration: signatories table'
);

// ── 2. Model ──────────────────────────────────────────────────────────

create_new_file("$root/app/Models/Signatory.php", <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signatory extends Model
{
    protected $fillable = [
        'key',
        'label',
        'name',
        'position',
    ];

    /**
     * Look up a configured signatory by key, e.g.
     * Signatory::forKey('ier_certifier'). Returns null if that key
     * hasn't been configured yet -- callers should fall back gracefully
     * (see JobPostingController::exportIER()) rather than assume it
     * exists.
     */
    public static function forKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
PHP,
    'Create Signatory model'
);

// ── 3. Controller ─────────────────────────────────────────────────────

create_new_file("$root/app/Http/Controllers/SignatoryController.php", <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\Signatory;
use Illuminate\Http\Request;

class SignatoryController extends Controller
{
    public function index()
    {
        $signatories = Signatory::orderBy('label')->get();
        return view('signatories.index', compact('signatories'));
    }

    public function create()
    {
        return view('signatories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:signatories,key'],
            'label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ]);

        Signatory::create($validated);

        return redirect()->route('signatories.index')->with('success', 'Signatory added.');
    }

    public function edit(Signatory $signatory)
    {
        return view('signatories.edit', compact('signatory'));
    }

    public function update(Request $request, Signatory $signatory)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:signatories,key,' . $signatory->id],
            'label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ]);

        $signatory->update($validated);

        return redirect()->route('signatories.index')->with('success', 'Signatory updated.');
    }

    public function destroy(Signatory $signatory)
    {
        $signatory->delete();

        return back()->with('success', 'Signatory deleted.');
    }
}
PHP,
    'Create SignatoryController'
);

// ── 4. Views ──────────────────────────────────────────────────────────

create_new_file("$root/resources/views/signatories/index.blade.php", <<<'BLADE'
@extends('layouts.app')

@section('title', 'Signatories')
@section('page-title', 'Signatories')

@section('content')
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<p class="text-muted small mb-3">
    People whose name and title appear on generated documents and emails (IER exports, qualification
    notices, offer letters, etc.). Each document template looks up a signatory by its <strong>key</strong> --
    changing a signatory's name/title here updates every document that references that key, with no need
    to edit the templates themselves.
</p>

<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('signatories.create') }}" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">
        <i class="bi bi-plus-lg me-1"></i> New signatory
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Key</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($signatories as $s)
                <tr>
                    <td class="fw-medium">{{ $s->label }}</td>
                    <td><code>{{ $s->key }}</code></td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>
                    <td class="text-end">
                        <a href="{{ route('signatories.edit', $s->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('signatories.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this signatory? Any document that references this key will fall back to a generic default.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No signatories configured yet. Documents currently fall back to generic defaults.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
BLADE,
    'Create signatories/index.blade.php'
);

create_new_file("$root/resources/views/signatories/create.blade.php", <<<'BLADE'
@extends('layouts.app')

@section('title', 'New Signatory')
@section('page-title', 'New Signatory')

@section('content')
<div class="card">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('signatories.store') }}">
            @csrf
            @include('signatories._form')
            <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save</button>
            <a href="{{ route('signatories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
BLADE,
    'Create signatories/create.blade.php'
);

create_new_file("$root/resources/views/signatories/edit.blade.php", <<<'BLADE'
@extends('layouts.app')

@section('title', 'Edit Signatory')
@section('page-title', 'Edit Signatory')

@section('content')
<div class="card">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('signatories.update', $signatory->id) }}">
            @csrf
            @method('PUT')
            @include('signatories._form', ['signatory' => $signatory])
            <button type="submit" class="btn" style="background-color: var(--hr-primary); color: #fff;">Save</button>
            <a href="{{ route('signatories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
BLADE,
    'Create signatories/edit.blade.php'
);

create_new_file("$root/resources/views/signatories/_form.blade.php", <<<'BLADE'
@if ($errors->any())
<div class="alert alert-danger small py-2">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<div class="mb-3">
    <label class="form-label small fw-medium">Key</label>
    <input type="text" name="key" class="form-control form-control-sm" style="max-width:320px;"
           value="{{ old('key', $signatory->key ?? '') }}" placeholder="e.g. ier_certifier" required>
    <div class="form-text">
        Fixed identifier a document template looks up -- letters, numbers, dashes, underscores only.
        Ask whoever is wiring up a new document what key it expects, or agree on one together
        (e.g. <code>qualification_notice_signatory</code>).
    </div>
</div>

<div class="mb-3">
    <label class="form-label small fw-medium">Label</label>
    <input type="text" name="label" class="form-control form-control-sm"
           value="{{ old('label', $signatory->label ?? '') }}" placeholder="e.g. IER Certifying Officer" required>
    <div class="form-text">Human-readable description shown in this list -- free to reword any time.</div>
</div>

<div class="mb-3">
    <label class="form-label small fw-medium">Name</label>
    <input type="text" name="name" class="form-control form-control-sm"
           value="{{ old('name', $signatory->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label small fw-medium">Position / Title</label>
    <input type="text" name="position" class="form-control form-control-sm"
           value="{{ old('position', $signatory->position ?? '') }}" placeholder="e.g. Human Resource Management Officer" required>
</div>
BLADE,
    'Create signatories/_form.blade.php (shared create/edit fields)'
);

// ── 5. Routes ─────────────────────────────────────────────────────────

$routesFile = "$root/routes/web.php";

apply_patch(
    $routesFile,
    <<<'OLD'
// Activity Log Book (added by install_activity_log_book.php)
Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
OLD,
    <<<'NEW'
// Signatories -- names/titles referenced by document/email templates
Route::get('/signatories', [\App\Http\Controllers\SignatoryController::class, 'index'])->name('signatories.index');
Route::get('/signatories/create', [\App\Http\Controllers\SignatoryController::class, 'create'])->name('signatories.create');
Route::post('/signatories', [\App\Http\Controllers\SignatoryController::class, 'store'])->name('signatories.store');
Route::get('/signatories/{signatory}/edit', [\App\Http\Controllers\SignatoryController::class, 'edit'])->name('signatories.edit');
Route::put('/signatories/{signatory}', [\App\Http\Controllers\SignatoryController::class, 'update'])->name('signatories.update');
Route::delete('/signatories/{signatory}', [\App\Http\Controllers\SignatoryController::class, 'destroy'])->name('signatories.destroy');

// Activity Log Book (added by install_activity_log_book.php)
Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs.index');
NEW,
    'Add signatories.* routes'
);

// ── 6. Sidebar nav link ───────────────────────────────────────────────

$navFile = "$root/resources/views/layouts/app.blade.php";

apply_patch(
    $navFile,
    <<<'OLD'
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Appointment & onboarding">
OLD,
    <<<'NEW'
                <a href="{{ route('signatories.index') }}" class="nav-link {{ request()->routeIs('signatories.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Signatories">
                    <i class="bi bi-pen"></i> <span class="nav-label">Signatories</span>
                </a>
                <a href="{{ route('appointments.index') }}" class="nav-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Appointment & onboarding">
NEW,
    'Add "Signatories" link to the sidebar nav'
);

// ── 7. Wire into the IER export as the first concrete usage ────────────

$postingCtrl = "$root/app/Http/Controllers/JobPostingController.php";

apply_patch(
    $postingCtrl,
    <<<'OLD'
        $sheet->setCellValue('O' . $footerStart, 'Prepared and certified correct by:');
        $sheet->setCellValue('O' . ($footerStart + 3), strtoupper(auth()->user()->name ?? ''));
        $sheet->setCellValue('O' . ($footerStart + 4), 'Human Resource Management Officer');
        $sheet->setCellValue('O' . ($footerStart + 5), 'Date: _______________');
OLD,
    <<<'NEW'
        // Prefer a configured signatory (Signatories admin page, key
        // 'ier_certifier'). Falls back to the logged-in user's name +
        // a generic title if none has been configured yet, so this
        // doesn't break before HR sets one up.
        $ierSignatory = \App\Models\Signatory::forKey('ier_certifier');

        $sheet->setCellValue('O' . $footerStart, 'Prepared and certified correct by:');
        $sheet->setCellValue('O' . ($footerStart + 3), strtoupper($ierSignatory->name ?? auth()->user()->name ?? ''));
        $sheet->setCellValue('O' . ($footerStart + 4), $ierSignatory->position ?? 'Human Resource Management Officer');
        $sheet->setCellValue('O' . ($footerStart + 5), 'Date: _______________');
NEW,
    'exportIER(): use configured Signatory (key=ier_certifier) instead of hardcoded title'
);

echo "\nDone. Next steps:\n";
echo "  1. php artisan migrate\n";
echo "  2. Visit /signatories and add one with key 'ier_certifier' to see it take effect\n";
echo "     on the next IER export.\n";
echo "  3. Send over the qualification notice email template (and any others) to wire\n";
echo "     those in the same way.\n";
