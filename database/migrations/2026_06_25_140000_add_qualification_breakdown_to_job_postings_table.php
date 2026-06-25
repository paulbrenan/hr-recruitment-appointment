<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('qualification_education')->nullable()->after('qualification_standards');
            $table->text('qualification_training')->nullable()->after('qualification_education');
            $table->text('qualification_experience')->nullable()->after('qualification_training');
            $table->text('qualification_eligibility')->nullable()->after('qualification_experience');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn([
                'qualification_education',
                'qualification_training',
                'qualification_experience',
                'qualification_eligibility',
            ]);
        });
    }
};