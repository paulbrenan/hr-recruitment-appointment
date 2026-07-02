<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->string('memo_pdf_path')->nullable();
        });
    }

    public function down()
    {
        Schema::table('pdf_import_batches', function (Blueprint $table) {
            $table->dropColumn('memo_pdf_path');
        });
    }
};