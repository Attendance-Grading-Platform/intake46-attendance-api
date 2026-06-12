<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add start_time and end_time to engagement_sessions so each session
 * has a concrete time window.  Used by the time-locked scanner (Update 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_sessions', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('session_date');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('engagement_sessions', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
