<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            // Fixed slug that document/email templates look up by, e.g.
            // 'ier_certifier', 'qualification_notice_signatory',
            // 'offer_letter_signatory'. Templates reference this, NOT
            // the human-readable label below, so the label can be
            // reworded freely without breaking anything.
            $table->string('key')->unique();
            // Human-readable description shown in the admin UI, e.g.
            // "IER Certifying Officer" or "HRMPSB Chairperson".
            $table->string('label');
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};