<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('position_title')->nullable();
            $table->string('item_number')->nullable();
            $table->enum('appointment_status', [
                'permanent',
                'temporary',
                'provisional',
                'casual',
                'job_order',
                'ojt',
            ])->default('job_order');
            $table->date('appointment_date')->nullable();
            $table->date('onboarding_date')->nullable();
            $table->string('appointment_paper_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};