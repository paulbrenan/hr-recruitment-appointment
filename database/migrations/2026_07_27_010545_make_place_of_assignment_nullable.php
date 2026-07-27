<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * place_of_assignment was intentionally dropped as a tracked field
 * (JobPostingController now passes null for it in store(),
 * mergeVacanciesInto(), and syncSingleLocation()), but the column is
 * still NOT NULL on both tables, so every insert fails with:
 *   SQLSTATE[23000]: Integrity constraint violation: 1048
 *   Column 'place_of_assignment' cannot be null
 *
 * This makes the column nullable on both job_posting_locations (the
 * per-location table the controller actually inserts into) and
 * job_postings (the legacy single-column mirror kept in sync via
 * updateQuietly()), rather than forcing a fake placeholder value into
 * a field you no longer want to collect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posting_locations', function (Blueprint $table) {
            $table->string('place_of_assignment')->nullable()->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('place_of_assignment')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reversing this would require backfilling a non-null value for
        // every existing null row first (otherwise the down-migration
        // itself would fail with the same NOT NULL violation), which
        // isn't meaningful data to invent. Left intentionally
        // unimplemented -- restore from a backup if you ever need to
        // roll back past this migration.
    }
};
