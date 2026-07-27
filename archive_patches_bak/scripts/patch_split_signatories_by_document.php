<?php
/**
 * patch_split_signatories_by_document.php
 *
 * Replaces the generic single `signatories` table (added by
 * patch_add_signatories.php, already run) with SEPARATE tables per
 * document type: `ier_signatories` and `qualification_notice_signatories`
 * to start, designed so more document types can be added the same way
 * later (copy the pattern, don't try to force everything into one shared
 * table).
 *
 * Each table has a `role_label` column, not just one name/title pair --
 * some documents need more than one signature block (e.g. "Prepared by"
 * + "Approved by"), so a document type can hold multiple rows rather
 * than being locked to exactly one signatory.
 *
 * This patch:
 *   1. Drops the generic `signatories` table and deletes its model/
 *      controller/views.
 *   2. Creates `ier_signatories` and `qualification_notice_signatories`
 *      tables, models, controllers.
 *   3. Rebuilds the Signatories admin page as ONE page with two
 *      sections (one per document type) rather than separate nav links
 *      per type -- keeps the sidebar from growing a new entry every
 *      time a document type is added.
 *   4. Rewires exportIER() to use IERSignatory instead of the generic
 *      Signatory model.
 *
 * The qualification_notice_signatories table/CRUD is ready but NOT
 * wired into an actual email template yet -- that file hasn't been
 * provided. Send it over and I'll wire it the same way exportIER() is
 * wired here.
 *
 * IMPORTANT: this assumes patch_add_signatories.php was already run
 * (per your confirmation). If it wasn't, this patch will abort on the
 * revert steps since there'd be nothing to revert.
 *
 * Run once from the project root:
 *   php patch_split_signatories_by_document.php
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
        fwrite(STDERR, "[SKIP] $label -- file already exists at $path.\n");
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
    echo "[OK] $label\n";
}

function remove_old_file($path, $label) {
    if (!file_exists($path)) {
        echo "[SKIP] $label -- not found, already removed?\n";
        return;
    }
    copy($path, $path . '.bak');
    unlink($path);
    echo "[OK] Removed $label (backed up to .bak)\n";
}

$root = __DIR__;

// ── 1. Drop the generic signatories table ────────────────────────────

$dropMigrationPath = "$root/database/migrations/" . date('Y_m_d_His') . "_drop_signatories_table.php";
create_new_file($dropMigrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('signatories');
    }

    public function down(): void
    {
        // Intentionally not recreated -- superseded by per-document
        // signatory tables (ier_signatories, qualification_notice_signatories).
    }
};
PHP,
    'Create migration: drop the old generic signatories table'
);

// ── 2. Remove the generic model/controller/views ─────────────────────

remove_old_file("$root/app/Models/Signatory.php", 'app/Models/Signatory.php');
remove_old_file("$root/app/Http/Controllers/SignatoryController.php", 'app/Http/Controllers/SignatoryController.php');
remove_old_file("$root/resources/views/signatories/create.blade.php", 'resources/views/signatories/create.blade.php');
remove_old_file("$root/resources/views/signatories/edit.blade.php", 'resources/views/signatories/edit.blade.php');
remove_old_file("$root/resources/views/signatories/_form.blade.php", 'resources/views/signatories/_form.blade.php');
// index.blade.php gets REPLACED (not just removed) further down.

// ── 3. New per-document tables ────────────────────────────────────────

create_new_file(
    "$root/database/migrations/" . date('Y_m_d_His', time() + 1) . "_create_ier_signatories_table.php",
    <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ier_signatories', function (Blueprint $table) {
            $table->id();
            // e.g. "Certifying Officer" -- a document can have more than
            // one signature block, this distinguishes them.
            $table->string('role_label');
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ier_signatories');
    }
};
PHP,
    'Create migration: ier_signatories table'
);

create_new_file(
    "$root/database/migrations/" . date('Y_m_d_His', time() + 2) . "_create_qualification_notice_signatories_table.php",
    <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualification_notice_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('role_label');
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_notice_signatories');
    }
};
PHP,
    'Create migration: qualification_notice_signatories table'
);

// ── 4. Models ─────────────────────────────────────────────────────────

create_new_file("$root/app/Models/IERSignatory.php", <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IERSignatory extends Model
{
    protected $table = 'ier_signatories';

    protected $fillable = ['role_label', 'name', 'position'];
}
PHP,
    'Create IERSignatory model'
);

create_new_file("$root/app/Models/QualificationNoticeSignatory.php", <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualificationNoticeSignatory extends Model
{
    protected $table = 'qualification_notice_signatories';

    protected $fillable = ['role_label', 'name', 'position'];
}
PHP,
    'Create QualificationNoticeSignatory model'
);

// ── 5. Controllers ────────────────────────────────────────────────────

create_new_file("$root/app/Http/Controllers/IERSignatoryController.php", <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\IERSignatory;
use Illuminate\Http\Request;

class IERSignatoryController extends Controller
{
    private function rules(): array
    {
        return [
            'role_label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }

    public function store(Request $request)
    {
        IERSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'IER signatory added.');
    }

    public function update(Request $request, IERSignatory $ierSignatory)
    {
        $ierSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'IER signatory updated.');
    }

    public function destroy(IERSignatory $ierSignatory)
    {
        $ierSignatory->delete();
        return back()->with('success', 'IER signatory deleted.');
    }
}
PHP,
    'Create IERSignatoryController'
);

create_new_file("$root/app/Http/Controllers/QualificationNoticeSignatoryController.php", <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\QualificationNoticeSignatory;
use Illuminate\Http\Request;

class QualificationNoticeSignatoryController extends Controller
{
    private function rules(): array
    {
        return [
            'role_label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }

    public function store(Request $request)
    {
        QualificationNoticeSignatory::create($request->validate($this->rules()));
        return back()->with('success', 'Qualification notice signatory added.');
    }

    public function update(Request $request, QualificationNoticeSignatory $qualificationNoticeSignatory)
    {
        $qualificationNoticeSignatory->update($request->validate($this->rules()));
        return back()->with('success', 'Qualification notice signatory updated.');
    }

    public function destroy(QualificationNoticeSignatory $qualificationNoticeSignatory)
    {
        $qualificationNoticeSignatory->delete();
        return back()->with('success', 'Qualification notice signatory deleted.');
    }
}
PHP,
    'Create QualificationNoticeSignatoryController'
);

// ── 6. Rebuild the index page: one page, one section per document type ─

$signatoriesIndexPath = "$root/resources/views/signatories/index.blade.php";
if (file_exists($signatoriesIndexPath)) {
    copy($signatoriesIndexPath, $signatoriesIndexPath . '.bak');
    unlink($signatoriesIndexPath);
}

create_new_file($signatoriesIndexPath, <<<'BLADE'
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

<p class="text-muted small mb-4">
    People whose name and title appear on generated documents and emails. Each document type has its own
    list below -- a document can have more than one signature block (e.g. "Prepared by" and "Approved by"),
    so add as many rows per section as that document actually needs.
</p>

{{-- IER --}}
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">IER (Initial Evaluation Result) Export</h6>
            <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" data-bs-toggle="modal" data-bs-target="#addIerSignatoryModal">
                <i class="bi bi-plus-lg me-1"></i> Add
            </button>
        </div>
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Role</th><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ierSignatories as $s)
                <tr>
                    <td>{{ $s->role_label }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editIerSignatoryModal{{ $s->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('ier-signatories.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this signatory?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editIerSignatoryModal{{ $s->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('ier-signatories.update', $s->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="modal-header"><h6 class="modal-title">Edit IER signatory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" value="{{ $s->role_label }}" required></div>
                                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $s->name }}" required></div>
                                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" value="{{ $s->position }}" required></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">No IER signatories yet -- exports fall back to a generic default.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addIerSignatoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('ier-signatories.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Add IER signatory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" placeholder="e.g. Certifying Officer" required></div>
                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" placeholder="e.g. Human Resource Management Officer" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Qualification Notice --}}
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Qualification Notice Email</h6>
            <button type="button" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;" data-bs-toggle="modal" data-bs-target="#addQnSignatoryModal">
                <i class="bi bi-plus-lg me-1"></i> Add
            </button>
        </div>
        <p class="text-muted small">Not wired into the qualification notice email yet -- send that template over and it'll use these the same way the IER export uses the list above.</p>
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Role</th><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($qualificationNoticeSignatories as $s)
                <tr>
                    <td>{{ $s->role_label }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editQnSignatoryModal{{ $s->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('qualification-notice-signatories.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this signatory?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editQnSignatoryModal{{ $s->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('qualification-notice-signatories.update', $s->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="modal-header"><h6 class="modal-title">Edit signatory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" value="{{ $s->role_label }}" required></div>
                                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $s->name }}" required></div>
                                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" value="{{ $s->position }}" required></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">None yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addQnSignatoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('qualification-notice-signatories.store') }}" method="POST">
                @csrf
                <div class="modal-header"><h6 class="modal-title">Add signatory</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" placeholder="e.g. Approved by" required></div>
                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--hr-primary); color: #fff;">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- To add another document type later: copy one of the two sections
     above, make a new migration/model/controller following the same
     ier_signatories / IERSignatory / IERSignatoryController pattern,
     and add its routes below the existing ones in routes/web.php. --}}
@endsection
BLADE,
    'Create new signatories/index.blade.php with IER + Qualification Notice sections'
);

// ── 7. Controller feeding both sections into the one index page ────────

create_new_file("$root/app/Http/Controllers/SignatoriesPageController.php", <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\IERSignatory;
use App\Models\QualificationNoticeSignatory;

class SignatoriesPageController extends Controller
{
    public function index()
    {
        $ierSignatories = IERSignatory::orderBy('role_label')->get();
        $qualificationNoticeSignatories = QualificationNoticeSignatory::orderBy('role_label')->get();

        return view('signatories.index', compact('ierSignatories', 'qualificationNoticeSignatories'));
    }
}
PHP,
    'Create SignatoriesPageController (feeds both sections into the one index page)'
);

// ── 8. Routes: replace the old generic ones with the new set ───────────

$routesFile = "$root/routes/web.php";

apply_patch(
    $routesFile,
    <<<'OLD'
// Signatories -- names/titles referenced by document/email templates
Route::get('/signatories', [\App\Http\Controllers\SignatoryController::class, 'index'])->name('signatories.index');
Route::get('/signatories/create', [\App\Http\Controllers\SignatoryController::class, 'create'])->name('signatories.create');
Route::post('/signatories', [\App\Http\Controllers\SignatoryController::class, 'store'])->name('signatories.store');
Route::get('/signatories/{signatory}/edit', [\App\Http\Controllers\SignatoryController::class, 'edit'])->name('signatories.edit');
Route::put('/signatories/{signatory}', [\App\Http\Controllers\SignatoryController::class, 'update'])->name('signatories.update');
Route::delete('/signatories/{signatory}', [\App\Http\Controllers\SignatoryController::class, 'destroy'])->name('signatories.destroy');
OLD,
    <<<'NEW'
// Signatories -- one page (signatories.index), one section per document
// type. Add a new document type by copying the ier-signatories.* group
// below with a new prefix/controller, NOT by adding fields to these.
Route::get('/signatories', [\App\Http\Controllers\SignatoriesPageController::class, 'index'])->name('signatories.index');

Route::post('/signatories/ier', [\App\Http\Controllers\IERSignatoryController::class, 'store'])->name('ier-signatories.store');
Route::put('/signatories/ier/{ierSignatory}', [\App\Http\Controllers\IERSignatoryController::class, 'update'])->name('ier-signatories.update');
Route::delete('/signatories/ier/{ierSignatory}', [\App\Http\Controllers\IERSignatoryController::class, 'destroy'])->name('ier-signatories.destroy');

Route::post('/signatories/qualification-notice', [\App\Http\Controllers\QualificationNoticeSignatoryController::class, 'store'])->name('qualification-notice-signatories.store');
Route::put('/signatories/qualification-notice/{qualificationNoticeSignatory}', [\App\Http\Controllers\QualificationNoticeSignatoryController::class, 'update'])->name('qualification-notice-signatories.update');
Route::delete('/signatories/qualification-notice/{qualificationNoticeSignatory}', [\App\Http\Controllers\QualificationNoticeSignatoryController::class, 'destroy'])->name('qualification-notice-signatories.destroy');
NEW,
    'Replace generic signatories.* routes with per-document-type routes'
);

// ── 9. Rewire exportIER() to use IERSignatory instead of Signatory ─────

$postingCtrl = "$root/app/Http/Controllers/JobPostingController.php";

apply_patch(
    $postingCtrl,
    <<<'OLD'
        // Prefer a configured signatory (Signatories admin page, key
        // 'ier_certifier'). Falls back to the logged-in user's name +
        // a generic title if none has been configured yet, so this
        // doesn't break before HR sets one up.
        $ierSignatory = \App\Models\Signatory::forKey('ier_certifier');

        $sheet->setCellValue('O' . $footerStart, 'Prepared and certified correct by:');
        $sheet->setCellValue('O' . ($footerStart + 3), strtoupper($ierSignatory->name ?? auth()->user()->name ?? ''));
        $sheet->setCellValue('O' . ($footerStart + 4), $ierSignatory->position ?? 'Human Resource Management Officer');
        $sheet->setCellValue('O' . ($footerStart + 5), 'Date: _______________');
OLD,
    <<<'NEW'
        // Prefer the first configured IER signatory (Signatories admin
        // page). Falls back to the logged-in user's name + a generic
        // title if none has been configured yet, so this doesn't break
        // before HR sets one up. If more than one IER signatory is ever
        // configured, this uses the first one -- the export template
        // only has room for a single "certified by" block.
        $ierSignatory = \App\Models\IERSignatory::orderBy('id')->first();

        $sheet->setCellValue('O' . $footerStart, 'Prepared and certified correct by:');
        $sheet->setCellValue('O' . ($footerStart + 3), strtoupper($ierSignatory->name ?? auth()->user()->name ?? ''));
        $sheet->setCellValue('O' . ($footerStart + 4), $ierSignatory->position ?? 'Human Resource Management Officer');
        $sheet->setCellValue('O' . ($footerStart + 5), 'Date: _______________');
NEW,
    'exportIER(): use IERSignatory model instead of the removed generic Signatory'
);

echo "\nDone. Next steps:\n";
echo "  1. php artisan migrate   (drops the old table, creates the two new ones)\n";
echo "  2. Visit /signatories -- you'll see two sections now (IER + Qualification Notice).\n";
echo "     Any signatory you'd already added under the old generic system needs to be\n";
echo "     re-added here, since the old table is dropped.\n";
echo "  3. Send over the qualification notice email template to wire that section in.\n";
