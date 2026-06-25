<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('requirement_items');
    }

    public function down(): void
    {
        // Intentionally not recreated -- see the dropped pivot migration
        // for context. Replaced by per-posting text columns.
    }
};