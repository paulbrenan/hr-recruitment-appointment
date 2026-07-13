<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Change column to string temporarily so we can safely alter the enum
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });

        // Step 2: Map any legacy values to the new pipeline values
        DB::table('job_postings')->where('status', 'draft')->update(['status' => 'open']);
        DB::table('job_postings')->where('status', 'filled')->update(['status' => 'closed']);
        // 'open' and 'closed' are unchanged

        // Step 3: Apply the new enum
        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['draft', 'open', 'filled', 'closed'])
                  ->default('draft')
                  ->change();
        });
    }
};