<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER migration -- adds scheduled_time as its OWN column rather than
 * changing scheduled_at from date to datetime. Deliberately avoids
 * Schema::table(...)->change(), which requires the doctrine/dbal
 * package to be installed; a plain ->time() add doesn't need it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orientation_schedules', function (Blueprint $table) {
            $table->time('scheduled_time')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orientation_schedules', function (Blueprint $table) {
            $table->dropColumn('scheduled_time');
        });
    }
};
