<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The applications.status enum lost 'interview_scheduled' when
     * 2026_07_02_000003_add_offer_statuses_to_applications_status_enum.php
     * renamed it to 'interview' -- but ApplicationController@updateStatus's
     * validation rule, InterviewScheduleController@storeForPosting, and
     * the job-postings pipeline view (show.blade.php) all still read/write
     * 'interview_scheduled' by that exact name. This adds it back
     * alongside the existing 'interview' value (which is left in place --
     * not removed -- in case any historical rows or other code paths
     * still depend on it) so both names are valid until the app is
     * standardized on one.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE applications
            MODIFY status ENUM(
                'submitted',
                'screening',
                'shortlisted',
                'interview',
                'interview_scheduled',
                'assessed',
                'ranked',
                'ranking_sent',
                'offer',
                'offer_sent',
                'offer_accepted',
                'offer_declined',
                'qualified',
                'not_qualified',
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE applications
            MODIFY status ENUM(
                'submitted',
                'screening',
                'shortlisted',
                'interview',
                'assessed',
                'ranked',
                'ranking_sent',
                'offer',
                'offer_sent',
                'offer_accepted',
                'offer_declined',
                'qualified',
                'not_qualified',
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};
