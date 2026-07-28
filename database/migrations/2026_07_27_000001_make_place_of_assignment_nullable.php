<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * JobPostingController::syncSingleLocation() and mergeVacanciesInto()
     * both intentionally insert place_of_assignment => null now that HR
     * enters a single "Vacancies" number instead of one row per school
     * (see the docblock on syncSingleLocation()). The column is still
     * NOT NULL from before that change, so every create/update on a job
     * posting currently fails with:
     *   SQLSTATE[23000]: Column 'place_of_assignment' cannot be null
     *
     * Uses a raw ALTER TABLE (not Schema::table()->change()) so this
     * doesn't require doctrine/dbal to be installed -- Laravel's schema
     * builder needs that package for column-modification migrations,
     * which isn't a default dependency as of Laravel 11+.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('job_posting_locations', 'place_of_assignment')) {
            return;
        }

        DB::statement('ALTER TABLE job_posting_locations MODIFY place_of_assignment VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Reverting requires every existing null to be backfilled first,
        // or this rollback will itself fail the same way the bug did.
        DB::statement('ALTER TABLE job_posting_locations MODIFY place_of_assignment VARCHAR(255) NOT NULL');
    }
};
