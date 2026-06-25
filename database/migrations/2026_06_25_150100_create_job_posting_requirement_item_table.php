<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posting_requirement_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_posting_id', 'requirement_item_id'], 'job_posting_requirement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_requirement_item');
    }
};