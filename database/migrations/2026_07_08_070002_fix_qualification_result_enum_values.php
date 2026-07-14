<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen the enum so it accepts both old and new values.
        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'disqualified', 'not_qualified') NULL");

        // Step 2: migrate any existing 'disqualified' rows to 'not_qualified'.
        DB::table('applications')
            ->where('qualification_result', 'disqualified')
            ->update(['qualification_result' => 'not_qualified']);

        // Step 3: narrow the enum to only the final allowed values.
        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'not_qualified') NULL");
    }

    public function down(): void
    {
        // Step 1: widen the enum again so 'disqualified' is valid.
        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'not_qualified', 'disqualified') NULL");

        // Step 2: migrate back.
        DB::table('applications')
            ->where('qualification_result', 'not_qualified')
            ->update(['qualification_result' => 'disqualified']);

        // Step 3: narrow back to original set.
        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'disqualified') NULL");
    }
};