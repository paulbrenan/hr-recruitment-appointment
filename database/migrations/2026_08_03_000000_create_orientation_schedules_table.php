<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_at');
            $table->string('place')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            // 'scheduled' or 'cancelled'. No draft/sent/accepted/declined
            // states like JobOffer -- the email goes out immediately on
            // creation (matching the interview-scheduling flow), so
            // there's no separate "send" step to track.
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientation_schedules');
    }
};
