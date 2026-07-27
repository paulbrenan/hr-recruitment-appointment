<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ier_signatories', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });

        Schema::table('qualification_notice_signatories', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });
    }

    public function down(): void
    {
        Schema::table('ier_signatories', function (Blueprint $table) {
            $table->string('role_label')->nullable()->after('id');
        });

        Schema::table('qualification_notice_signatories', function (Blueprint $table) {
            $table->string('role_label')->nullable()->after('id');
        });
    }
};