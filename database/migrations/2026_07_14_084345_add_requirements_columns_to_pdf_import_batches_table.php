<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->json('requirements')->nullable()->after('candidates');
            $table->json('newly_registered_titles')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['requirements', 'newly_registered_titles']);
        });
    }
};