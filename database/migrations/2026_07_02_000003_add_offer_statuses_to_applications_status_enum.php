<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The live `applications` table enum had drifted out of sync with the
     * migration file (someone edited create_applications_table.php's enum
     * list after it had already been migrated once, so the DB never got
     * the new values). This adds the missing statuses the app actually
     * writes ('offer_sent', 'offer_accepted', 'offer_declined') while
     * keeping every existing value, so no historical rows are affected.
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
                'assessed',
                'ranked',
                'ranking_sent',
                'offer',
                'offer_sent',
                'offer_accepted',
                'offer_declined',
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
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};
