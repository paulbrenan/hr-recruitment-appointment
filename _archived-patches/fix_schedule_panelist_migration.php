<?php

/**
 * fix_schedule_panelist_migration.php
 *
 * WHAT THIS DOES:
 *   Finds the migration file created by patch_schedule_panelists.php
 *   (*_create_interview_schedule_panelist_table.php) and replaces it with
 *   a version that uses a short explicit index name to avoid MySQL's
 *   64-character identifier limit.
 *
 * HOW TO RUN:
 *   php fix_schedule_panelist_migration.php     (from project root)
 *   php artisan migrate                         (afterward)
 *
 * DELETE this script after running.
 */

define('ROOT', __DIR__);

$migrationDir = ROOT . '/database/migrations';

// Find the migration file
$files = glob($migrationDir . '/*_create_interview_schedule_panelist_table.php');
if (empty($files)) {
    echo "❌ Could not find *_create_interview_schedule_panelist_table.php in database/migrations.\n";
    exit(1);
}

$migrationFile = $files[0];
echo "Found: $migrationFile\n";

// Back it up
$bak = $migrationFile . '.bak';
$i = 2;
while (file_exists($bak)) { $bak = $migrationFile . '.bak' . $i++; }
copy($migrationFile, $bak);
echo "  [bak] $bak\n";

// Write the fixed version with a short explicit index name
file_put_contents($migrationFile, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_schedule_panelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_schedule_id')
                  ->constrained('interview_schedules')
                  ->cascadeOnDelete();
            $table->foreignId('panelist_id')
                  ->constrained('panelists')
                  ->cascadeOnDelete();
            // Explicit short name — avoids MySQL 64-char identifier limit
            $table->unique(['interview_schedule_id', 'panelist_id'], 'isp_schedule_panelist_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedule_panelist');
    }
};
PHP);

echo "  [ok ] Migration rewritten with short index name.\n";
echo "\n✅ Done. Now run: php artisan migrate\n";
echo "   Then delete this script.\n";
