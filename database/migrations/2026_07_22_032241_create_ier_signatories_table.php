<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ier_signatories', function (Blueprint $table) {
            $table->id();
            // e.g. "Certifying Officer" -- a document can have more than
            // one signature block, this distinguishes them.
            $table->string('role_label');
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ier_signatories');
    }
};