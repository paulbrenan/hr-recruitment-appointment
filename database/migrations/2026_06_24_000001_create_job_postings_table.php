<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('duties_responsibilities')->nullable();
            $table->text('qualification_standards')->nullable();
            $table->string('place_of_assignment')->nullable();
            $table->string('employment_type')->nullable(); // regular, provisional, casual, job order, OJT
            $table->integer('vacancies')->default(1);
            $table->date('posted_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->enum('status', ['draft', 'open', 'filled', 'closed'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};