<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('job_posting_requirement_item');
    }

    public function down(): void
    {
        // Intentionally not recreated -- this table is being permanently
        // replaced by the mandatory_requirements/additional_requirements
        // text columns on job_postings. See the accompanying migrations.
    }
};