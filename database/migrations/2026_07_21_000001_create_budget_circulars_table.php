<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_circulars', function (Blueprint $table) {
            $table->id();

            // These three are filled in from the parsed PDF text when possible
            // (e.g. "NATIONAL BUDGET CIRCULAR No. 601", "Effective January 1, 2026"),
            // but staff can also type them in manually on the upload form --
            // OCR on a scanned circular won't always catch them cleanly.
            $table->string('circular_no')->nullable();
            $table->string('subject')->nullable();
            $table->date('effective_date')->nullable();

            $table->string('source_type', 10)->default('pdf'); // pdf | xlsx
            $table->string('source_file_path')->nullable();    // copy kept in storage/app/public/budget-circulars
            $table->string('original_filename')->nullable();

            // processing -> ready (parsed, awaiting staff review) -> applied (confirmed, now current)
            //            -> failed
            $table->string('status', 20)->default('processing');
            $table->text('error_message')->nullable();

            // Only one circular is "current" at a time -- that's the one
            // SalaryGrade::currentTableArray() reads from.
            $table->boolean('is_current')->default(false);

            $table->unsignedBigInteger('imported_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_circulars');
    }
};
