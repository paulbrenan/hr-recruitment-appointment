<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the hot-path columns identified across RecordsController,
     * JobOfferController, AppointmentController, InterviewScheduleController,
     * TalentPoolController, PortalController, CandidateAuthController and
     * DashboardController. All currently unindexed, so at higher applicant
     * counts (thousands+) these queries fall back to full table scans.
     *
     * Each index add is wrapped so a missing table/column, or an index that
     * already exists, is skipped quietly instead of failing the migration --
     * this doesn't depend on doctrine/dbal, just tries the add() and moves on.
     */
    public function up(): void
    {
        $this->addIndex('applications', 'job_posting_id');
        $this->addIndex('applications', 'candidate_id');
        $this->addIndex('applications', 'status');
        $this->addIndex('applications', 'transaction_number');
        $this->addIndex('applications', 'applied_at');
        // Speeds up the duplicate-application check in PortalController /
        // CandidateAuthController (candidate_id + job_posting_id looked up
        // together), and the location-scoped query in
        // InterviewScheduleController::storeForPosting.
        $this->addIndex('applications', ['candidate_id', 'job_posting_id']);
        $this->addIndex('applications', ['job_posting_id', 'status']);

        $this->addIndex('job_postings', 'status');
        $this->addIndex('job_postings', 'posted_at');
        $this->addIndex('job_postings', 'closes_at');

        $this->addIndex('job_offers', 'application_id');
        $this->addIndex('job_offers', 'status');

        $this->addIndex('appointments', 'application_id');

        $this->addIndex('interview_schedules', 'application_id');
        $this->addIndex('interview_schedules', 'status');
        $this->addIndex('interview_schedules', 'scheduled_at');

        $this->addIndex('talent_pools', 'candidate_id');
        $this->addIndex('talent_pools', 'application_id');

        // ActivityLogController::index() filters/orders by created_at
        // (latest() + whereDate()) on every request.
        $this->addIndex('activity_logs', 'created_at');
    }

    public function down(): void
    {
        $this->dropIndex('applications', 'job_posting_id');
        $this->dropIndex('applications', 'candidate_id');
        $this->dropIndex('applications', 'status');
        $this->dropIndex('applications', 'transaction_number');
        $this->dropIndex('applications', 'applied_at');
        $this->dropIndex('applications', ['candidate_id', 'job_posting_id']);
        $this->dropIndex('applications', ['job_posting_id', 'status']);

        $this->dropIndex('job_postings', 'status');
        $this->dropIndex('job_postings', 'posted_at');
        $this->dropIndex('job_postings', 'closes_at');

        $this->dropIndex('job_offers', 'application_id');
        $this->dropIndex('job_offers', 'status');

        $this->dropIndex('appointments', 'application_id');

        $this->dropIndex('interview_schedules', 'application_id');
        $this->dropIndex('interview_schedules', 'status');
        $this->dropIndex('interview_schedules', 'scheduled_at');

        $this->dropIndex('talent_pools', 'candidate_id');
        $this->dropIndex('talent_pools', 'application_id');

        $this->dropIndex('activity_logs', 'created_at');
    }

    /**
     * @param string|array $columns
     */
    private function addIndex(string $table, $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = (array) $columns;

        foreach ($columns as $col) {
            if (! Schema::hasColumn($table, $col)) {
                return; // skip this index if any referenced column is missing
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->index($columns);
            });
        } catch (QueryException $e) {
            // Duplicate key name / index already exists -- fine, skip it.
        }
    }

    /**
     * @param string|array $columns
     */
    private function dropIndex(string $table, $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropIndex((array) $columns);
            });
        } catch (QueryException $e) {
            // Index doesn't exist -- fine, skip it.
        }
    }
};
