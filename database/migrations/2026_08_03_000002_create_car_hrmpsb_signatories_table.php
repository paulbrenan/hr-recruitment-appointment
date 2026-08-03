<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Names/positions for the HRMPSB signature block used on the SG 16-23
     * CAR layout (Chairman, Members, Appointing Authority -- distinguished
     * by keywords in `position`, e.g. "HRMPSB Chairman", "HRMPSB Member",
     * "Appointing Authority").
     */
    public function up(): void
    {
        Schema::create('car_hrmpsb_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_hrmpsb_signatories');
    }
};
