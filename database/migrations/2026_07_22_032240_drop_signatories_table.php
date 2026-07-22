<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('signatories');
    }

    public function down(): void
    {
        // Intentionally not recreated -- superseded by per-document
        // signatory tables (ier_signatories, qualification_notice_signatories).
    }
};