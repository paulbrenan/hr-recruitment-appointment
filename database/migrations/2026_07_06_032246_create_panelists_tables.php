<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global panelist pool
        Schema::create('panelists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Pivot: which panelists are assigned to a posting and their availability
        Schema::create('job_posting_panelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('panelist_id')->constrained('panelists')->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->unique(['job_posting_id', 'panelist_id']);
            $table->timestamps();
        });

        // Seed 6 mock panelists
        DB::table('panelists')->insert([
            ['name' => 'Dr. Maria Santos',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Engr. Jose Reyes',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Prof. Ana Dela Cruz',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Atty. Ramon Villanueva','created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dr. Lourdes Mendoza',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mr. Carlos Bautista',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_panelist');
        Schema::dropIfExists('panelists');
    }
};