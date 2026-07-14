<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->json('candidates'); // array of candidate posting rows, see PdfImportBatch model docblock
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_import_batches');
    }
};