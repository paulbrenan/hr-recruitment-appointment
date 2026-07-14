<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed', 'archived'])
                  ->default('open')
                  ->change();
        });
    }

    public function down(): void
    {
        // Any postings already archived are bumped back to 'closed' first,
        // since 'archived' won't exist in the reverted enum.
        \Illuminate\Support\Facades\DB::table('job_postings')
            ->where('status', 'archived')
            ->update(['status' => 'closed']);

        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }
};
