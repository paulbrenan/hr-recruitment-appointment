<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posting_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')
                  ->constrained('job_postings')
                  ->cascadeOnDelete();
            $table->text('place_of_assignment');
            $table->unsignedInteger('vacancies')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_locations');
    }
};