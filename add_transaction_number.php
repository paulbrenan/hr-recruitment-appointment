<?php
/**
 * add_transaction_number.php
 *
 * Adds a unique transaction number to every application.
 * Format: APP-YYYYMMDD-XXXXXX  (e.g. APP-20260629-A3F9K2)
 *
 * What this script does:
 *   1. Creates the Laravel migration file
 *   2. Patches app/Models/Application.php  → adds $fillable entry + helper method
 *   3. Patches app/Http/Controllers/PortalController.php → generate on apply()
 *   4. Backfills existing rows via a one-time SQL file (run manually in phpMyAdmin)
 *
 * Usage:
 *   php add_transaction_number.php          (from project root)
 *   php artisan migrate
 *   Import backfill_transaction_numbers.sql in phpMyAdmin (optional, for existing rows)
 *   Delete this script.
 */

// ─── helpers ─────────────────────────────────────────────────────────────────

function die_loud(string $msg): void
{
    fwrite(STDERR, "\n[ABORTED] $msg\n\n");
    exit(1);
}

function backup(string $path): void
{
    if (!file_exists($path)) die_loud("File not found: $path");
    $bak = $path . '.bak';
    $n   = 1;
    while (file_exists($bak)) { $n++; $bak = $path . '.bak' . $n; }
    copy($path, $bak) or die_loud("Cannot backup $path");
    echo "  Backed up → " . basename($bak) . "\n";
}

function patch(string $content, string $old, string $new, string $label): string
{
    $hits = substr_count($content, $old);
    if ($hits !== 1) die_loud("Patch '$label': expected 1 match, found $hits. File may have drifted.");
    return str_replace($old, $new, $content);
}

function write(string $path, string $body, string $label, bool $overwrite = false): void
{
    if (!$overwrite && file_exists($path)) die_loud("$label already exists at $path — script may have already run.");
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $body) !== false or die_loud("Cannot write $path");
    echo ($overwrite ? '  Replaced' : '  Created') . " $label\n";
}

$root = __DIR__;

// ─── 1. Migration ─────────────────────────────────────────────────────────────

$ts        = date('Y_m_d_His');
$migPath   = "$root/database/migrations/{$ts}_add_transaction_number_to_applications_table.php";

write($migPath, <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint \$table) {
            // Placed after `id` so it surfaces prominently in queries/views.
            \$table->string('transaction_number', 30)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint \$table) {
            \$table->dropColumn('transaction_number');
        });
    }
};
PHP, 'migration');

// ─── 2. Model patch ───────────────────────────────────────────────────────────

$modelPath = "$root/app/Models/Application.php";
$model     = file_get_contents($modelPath) ?: die_loud("Cannot read $modelPath");

backup($modelPath);

// 2a. Add transaction_number to $fillable — find the fillable array opening
//     The seeded model may look like:  protected $fillable = [
//     We look for any existing fillable entry to anchor on.
if (!str_contains($model, "'transaction_number'")) {

    // Try to insert before 'candidate_id' — the first fillable field
    if (str_contains($model, "'candidate_id'")) {
        $model = patch($model,
            "'candidate_id'",
            "'transaction_number',\n        'candidate_id'",
            'fillable: transaction_number before candidate_id'
        );
    } elseif (str_contains($model, 'protected $fillable')) {
        // Fallback: append before the closing bracket of fillable
        $model = patch($model,
            "protected \$fillable = [",
            "protected \$fillable = [\n        'transaction_number',",
            'fillable: transaction_number fallback'
        );
    } else {
        die_loud("Cannot locate \$fillable in Application model. Add 'transaction_number' manually.");
    }
}

// 2b. Inject a static generator method before the final closing brace
$generator = <<<'PHP'

    /**
     * Generate a unique, human-readable transaction number.
     * Format: APP-YYYYMMDD-XXXXXX
     *
     * Loops until a collision-free value is found (practically instant —
     * 36^6 = ~2.1 billion combinations).
     */
    public static function generateTransactionNumber(): string
    {
        do {
            $suffix = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $number = 'APP-' . date('Ymd') . '-' . $suffix;
        } while (static::where('transaction_number', $number)->exists());

        return $number;
    }
PHP;

// Insert before the last closing brace of the class
$lastBrace = strrpos($model, '}');
if ($lastBrace === false) die_loud('Cannot find closing brace of Application model.');
$model = substr($model, 0, $lastBrace) . $generator . "\n}\n";

write($modelPath, $model, 'Application model', overwrite: true);

// ─── 3. PortalController patch ────────────────────────────────────────────────

$ctrlPath = "$root/app/Http/Controllers/PortalController.php";
$ctrl     = file_get_contents($ctrlPath) ?: die_loud("Cannot read $ctrlPath. Run build_portal.php first.");

backup($ctrlPath);

// Replace the Application::create([...]) block inside apply() to add the field.
// We anchor on the unique 'job_posting_id' line inside the create call.
$ctrl = patch($ctrl,
    <<<'OLD'
        Application::create([
            'candidate_id'   => $candidate->id,
            'job_posting_id' => $posting->id,
            'status'         => 'submitted',
            'applied_at'     => now(),
            'notes'          => $request->input('cover_note'),
        ]);
OLD,
    <<<'NEW'
        Application::create([
            'transaction_number' => Application::generateTransactionNumber(),
            'candidate_id'       => $candidate->id,
            'job_posting_id'     => $posting->id,
            'status'             => 'submitted',
            'applied_at'         => now(),
            'notes'              => $request->input('cover_note'),
        ]);
NEW,
    'PortalController::apply() create block'
);

write($ctrlPath, $ctrl, 'PortalController', overwrite: true);

// ─── 4. Backfill SQL for existing rows ────────────────────────────────────────

// Generate 12 unique-looking numbers (matching seed data ids 1-12)
$used = [];
$backfillSql  = "-- Backfill transaction numbers for existing applications\n";
$backfillSql .= "-- Import this in phpMyAdmin AFTER running: php artisan migrate\n\n";

$date = date('Ymd');
for ($id = 1; $id <= 12; $id++) {
    do {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $suffix = '';
        for ($i = 0; $i < 6; $i++) $suffix .= $chars[random_int(0, 35)];
        $num = "APP-{$date}-{$suffix}";
    } while (in_array($num, $used, true));
    $used[] = $num;
    $backfillSql .= "UPDATE `applications` SET `transaction_number` = '$num' WHERE `id` = $id AND `transaction_number` IS NULL;\n";
}

$backfillPath = "$root/backfill_transaction_numbers.sql";
write($backfillPath, $backfillSql, 'backfill SQL');

// ─── Done ─────────────────────────────────────────────────────────────────────

echo <<<TXT

✅  Done! Next steps:

  1. php artisan migrate
       → adds transaction_number column (nullable, unique) to applications

  2. (Optional) Import backfill_transaction_numbers.sql in phpMyAdmin
       → fills in numbers for your 12 existing rows

  3. New applications submitted via the portal will auto-get a number like:
       APP-20260629-A3F9K2

  4. To show it on the "My Applications" page, add this inside the card in
     resources/views/portal/my-applications.blade.php after the position title:

       @if (\$app->transaction_number)
           <small class="text-muted d-block" style="font-size:0.72rem;letter-spacing:.04em;">
               <i class="bi bi-hash"></i> {{ \$app->transaction_number }}
           </small>
       @endif

  5. Delete this script and backfill_transaction_numbers.sql when done.

TXT;
