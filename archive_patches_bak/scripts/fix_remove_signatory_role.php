<?php
/**
 * fix_remove_signatory_role.php
 *
 * Removes the role_label field from both signatory tables
 * (ier_signatories, qualification_notice_signatories), leaving just
 * name and position, per request. Touches: migration (drop column),
 * both models, both controllers, and the signatories index page (both
 * sections -- table headers/rows, add modals, edit modals).
 *
 * HOW TO RUN:
 *   php fix_remove_signatory_role.php   (from project root)
 *   then: php artisan migrate
 * DELETE this script after running.
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

echo "\n=== fix_remove_signatory_role.php ===\n\n";

$migrationPath = ROOT . '/database/migrations/' . date('Y_m_d_His') . '_drop_role_label_from_signatories.php';
$ierModelPath = ROOT . '/app/Models/IERSignatory.php';
$qnModelPath = ROOT . '/app/Models/QualificationNoticeSignatory.php';
$ierCtrlPath = ROOT . '/app/Http/Controllers/IERSignatoryController.php';
$qnCtrlPath = ROOT . '/app/Http/Controllers/QualificationNoticeSignatoryController.php';
$indexPath = ROOT . '/resources/views/signatories/index.blade.php';

// ─── 1. Migration: drop role_label from both tables ─────────────────────

echo "[1] Creating migration to drop role_label from both tables...\n";

file_put_contents($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ier_signatories', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });

        Schema::table('qualification_notice_signatories', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });
    }

    public function down(): void
    {
        Schema::table('ier_signatories', function (Blueprint $table) {
            $table->string('role_label')->nullable()->after('id');
        });

        Schema::table('qualification_notice_signatories', function (Blueprint $table) {
            $table->string('role_label')->nullable()->after('id');
        });
    }
};
PHP
);
echo "  [ok ] Migration created: {$migrationPath}\n";

// ─── 2. Models: remove role_label from $fillable ─────────────────────────

echo "\n[2] Updating models...\n";

apply_patch(
    $ierModelPath,
    "    protected \$fillable = ['role_label', 'name', 'position'];",
    "    protected \$fillable = ['name', 'position'];",
    'IERSignatory: remove role_label from $fillable'
);

apply_patch(
    $qnModelPath,
    "    protected \$fillable = ['role_label', 'name', 'position'];",
    "    protected \$fillable = ['name', 'position'];",
    'QualificationNoticeSignatory: remove role_label from $fillable'
);

// ─── 3. Controllers: remove role_label validation rule ──────────────────

echo "\n[3] Updating controllers...\n";

apply_patch(
    $ierCtrlPath,
    "    private function rules(): array
    {
        return [
            'role_label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }",
    "    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }",
    'IERSignatoryController: remove role_label validation'
);

apply_patch(
    $qnCtrlPath,
    "    private function rules(): array
    {
        return [
            'role_label' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }",
    "    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
        ];
    }",
    'QualificationNoticeSignatoryController: remove role_label validation'
);

// ─── 4. Index page: remove Role column/inputs from both sections ────────

echo "\n[4] Updating signatories index page (IER section)...\n";

apply_patch(
    $indexPath,
    '        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Role</th><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ierSignatories as $s)
                <tr>
                    <td>{{ $s->role_label }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>',
    '        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ierSignatories as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>',
    'signatories/index.blade.php: IER table drops Role column'
);

apply_patch(
    $indexPath,
    '                                <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" value="{{ $s->role_label }}" required></div>
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
                <tr><td colspan="4" class="text-center text-muted py-3">No IER signatories yet -- exports fall back to a generic default.</td></tr>',
    '                                <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $s->name }}" required></div>
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
                <tr><td colspan="3" class="text-center text-muted py-3">No IER signatories yet -- exports fall back to a generic default.</td></tr>',
    'signatories/index.blade.php: IER edit modal drops Role field'
);

apply_patch(
    $indexPath,
    '                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" placeholder="e.g. Certifying Officer" required></div>
                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" placeholder="e.g. Human Resource Management Officer" required></div>',
    '                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" placeholder="e.g. Human Resource Management Officer" required></div>',
    'signatories/index.blade.php: IER add modal drops Role field'
);

echo "\n[5] Updating signatories index page (Qualification Notice section)...\n";

apply_patch(
    $indexPath,
    '        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Role</th><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($qualificationNoticeSignatories as $s)
                <tr>
                    <td>{{ $s->role_label }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>',
    '        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Position</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($qualificationNoticeSignatories as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->position }}</td>',
    'signatories/index.blade.php: Qualification Notice table drops Role column'
);

apply_patch(
    $indexPath,
    '                                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" value="{{ $s->role_label }}" required></div>
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
                <tr><td colspan="4" class="text-center text-muted py-3">None yet.</td></tr>',
    '                                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" value="{{ $s->name }}" required></div>
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
                <tr><td colspan="3" class="text-center text-muted py-3">None yet.</td></tr>',
    'signatories/index.blade.php: Qualification Notice edit modal drops Role field'
);

apply_patch(
    $indexPath,
    '                    <div class="mb-2"><label class="form-label small">Role</label><input type="text" name="role_label" class="form-control form-control-sm" placeholder="e.g. Approved by" required></div>
                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" required></div>',
    '                    <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control form-control-sm" required></div>',
    'signatories/index.blade.php: Qualification Notice add modal drops Role field'
);

echo "\n✅ Done.\n\n";
echo "WHAT CHANGED:\n";
echo "  - Both signatory types (IER, Qualification Notice) now only have\n";
echo "    Name and Position -- role_label removed everywhere (schema,\n";
echo "    models, controllers, admin page tables/modals).\n\n";
echo "NEXT STEP: run 'php artisan migrate' to actually drop the column.\n\n";
echo "STILL PENDING (separate from this fix): wiring\n";
echo "QualificationNoticeSignatory into the actual qualification notice\n";
echo "PDF/email -- send over resources/views/pdf/qualification-notice.blade.php\n";
echo "and I'll wire it in the same way exportIER() already uses\n";
echo "IERSignatory.\n\n";
echo "DELETE this script after running.\n";
