<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate any existing 'disqualified' rows to 'not_qualified' BEFORE
        // tightening the enum, so nothing gets silently truncated/lost.
        DB::table('applications')
            ->where('qualification_result', 'disqualified')
            ->update(['qualification_result' => 'not_qualified']);

        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'not_qualified') NULL");
    }

    public function down(): void
    {
        DB::table('applications')
            ->where('qualification_result', 'not_qualified')
            ->update(['qualification_result' => 'disqualified']);

        DB::statement("ALTER TABLE applications MODIFY qualification_result ENUM('qualified', 'disqualified') NULL");
    }
};