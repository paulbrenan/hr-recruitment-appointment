<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('budget_circular_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('grade'); // 1-33
            $table->unsignedTinyInteger('step');  // 1-8 (SG-33 only goes up to step 2)
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            // One amount per grade/step per circular -- re-running the parser
            // on the same import (e.g. after a manual correction) updates in
            // place instead of duplicating rows.
            $table->unique(['budget_circular_id', 'grade', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
