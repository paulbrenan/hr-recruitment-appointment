<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes: ProcessPdfImportJob::handle() calls
 *   $batch->update(['requirements' => ..., ...])
 * but no migration ever added a `requirements` column to
 * pdf_import_batches -- it only exists in the model's $fillable/$casts
 * (cast as 'array', same as `candidates`). This caused every PDF import
 * to fail with "Unknown column 'requirements'".
 *
 * Also adds `newly_registered_titles` for the same reason: it's declared
 * in $fillable and $casts (also cast as 'array') but nothing ever created
 * the column. Nothing currently writes to it, but adding it now prevents
 * hitting the identical bug later once something does.
 *
 * Both are nullable JSON columns, matching the existing `candidates`
 * column's type and the model's array casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('candidates');
            $table->json('newly_registered_titles')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'newly_registered_titles']);
        });
    }
};
