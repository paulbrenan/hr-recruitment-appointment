<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            // processing = job queued/running, ready = candidates are populated,
            // failed = job errored out (see error_message)
            $table->string('status')->default('processing')->after('original_filename');
            $table->text('error_message')->nullable()->after('status');
            $table->string('pdf_path')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message', 'pdf_path']);
        });
    }
};