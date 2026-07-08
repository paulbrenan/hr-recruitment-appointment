<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
                'hired',
                'rejected'
            ) NOT NULL DEFAULT 'submitted'
        ");
    }
};