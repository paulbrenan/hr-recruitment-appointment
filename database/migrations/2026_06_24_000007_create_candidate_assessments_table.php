<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('assessment_criteria_id')->constrained('assessment_criteria')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->text('evaluator_remarks')->nullable();
            $table->string('evaluated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_assessments');
    }
};