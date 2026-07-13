<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Stores the per-criterion checkbox results from the HR
            // qualification checklist, e.g.
            // {"education": true, "training": true, "experience": false, "eligibility": true, "notes": "..."}
            $table->json('qualification_check')->nullable()->after('notes');

            // Computed from qualification_check: null = not checked yet,
            // 'qualified' / 'disqualified' once HR runs the checklist.
            $table->enum('qualification_result', ['qualified', 'disqualified'])
                ->nullable()
                ->after('qualification_check');

            $table->timestamp('qualification_checked_at')->nullable()->after('qualification_result');
            $table->timestamp('qualification_notified_at')->nullable()->after('qualification_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'qualification_check',
                'qualification_result',
                'qualification_checked_at',
                'qualification_notified_at',
            ]);
        });
    }
};
