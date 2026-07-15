<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Tracks when the Step 3 "Send all emails" button last notified
            // this applicant (qualified+schedule letter, or disqualification
            // if not qualified / qualified-but-not-yet-scheduled). Separate
            // from qualification_notified_at, which tracks the Step 2 button.
            $table->timestamp('schedule_notice_sent_at')->nullable()->after('qualification_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('schedule_notice_sent_at');
        });
    }
};
