<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen job_postings.place_of_assignment from varchar(255) to text.
     *
     * Confirmed real crash: confirming a PDF import for "Project
     * Development Officer I" threw SQLSTATE[22001] "Data too long for
     * column 'place_of_assignment'" — the value being inserted was well
     * over 255 characters. varchar(255) was never really big enough for
     * this column even in the best case (a school row can legitimately
     * list several adopted campuses, e.g. "Area J ES, Bulihan ES,
     * Cabulusan ES, Urdaneta ES, Magallanes ES (General Mariano
     * Alvarez)"), so this needs to be text regardless of what else is
     * causing this particular value to be so unusually large.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('place_of_assignment')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('place_of_assignment', 255)->nullable()->change();
        });
    }
};
