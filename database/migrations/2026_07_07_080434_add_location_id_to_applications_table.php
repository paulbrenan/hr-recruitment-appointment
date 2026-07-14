<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('applications', 'job_posting_location_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->foreignId('job_posting_location_id')
                      ->nullable()
                      ->after('job_posting_id')
                      ->constrained('job_posting_locations')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('applications', 'job_posting_location_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropForeign(['job_posting_location_id']);
                $table->dropColumn('job_posting_location_id');
            });
        }
    }
};
