<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_schedule_panelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_schedule_id')
                  ->constrained('interview_schedules')
                  ->cascadeOnDelete();
            $table->foreignId('panelist_id')
                  ->constrained('panelists')
                  ->cascadeOnDelete();
            // Explicit short name — avoids MySQL 64-char identifier limit
            $table->unique(['interview_schedule_id', 'panelist_id'], 'isp_schedule_panelist_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedule_panelist');
    }
};