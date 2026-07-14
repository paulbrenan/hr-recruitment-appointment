<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds fields needed to send email invitations/reminders for
     * open ranking sessions, interviews, and exams.
     *
     * - interviewer_email: interviewer_name already existed as free text
     *   with no email, so invitations couldn't reach them. Nullable,
     *   since older rows won't have it and it's optional on the form.
     * - reminder_sent_at: marks when the 24hr-before reminder was sent,
     *   so the scheduled job never sends the same reminder twice.
     */
    public function up(): void
    {
        Schema::table('interview_schedules', function (Blueprint $table) {
            $table->string('interviewer_email')->nullable()->after('interviewer_name');
            $table->timestamp('reminder_sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('interview_schedules', function (Blueprint $table) {
            $table->dropColumn(['interviewer_email', 'reminder_sent_at']);
        });
    }
};
