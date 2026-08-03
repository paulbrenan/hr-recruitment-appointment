<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Names/positions for the District Sub-Committee signature block used
     * on the SG 11-15 CAR layout (Chairman, Co-Chairman(s), Members --
     * distinguished by keywords in `position`, e.g. "District
     * Sub-Committee Chairman", "District Sub-Committee Co-Chairman",
     * "District Sub-Committee Member").
     */
    public function up(): void
    {
        Schema::create('car_district_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_district_signatories');
    }
};
