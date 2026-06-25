<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('mandatory_requirements')->nullable()->after('qualification_eligibility');
            $table->text('additional_requirements')->nullable()->after('mandatory_requirements');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['mandatory_requirements', 'additional_requirements']);
        });
    }
};