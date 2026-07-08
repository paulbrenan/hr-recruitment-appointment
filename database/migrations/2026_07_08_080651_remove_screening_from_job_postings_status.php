<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Move any postings stuck on 'screening' to 'interview_scheduled'
        DB::table('job_postings')->where('status', 'screening')->update(['status' => 'interview_scheduled']);

        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });
        Schema::table('job_postings', function (Blueprint $table) {
            $table->enum('status', ['open', 'screening', 'interview_scheduled', 'ranking', 'closed'])
                  ->default('open')
                  ->change();
        });
    }
};