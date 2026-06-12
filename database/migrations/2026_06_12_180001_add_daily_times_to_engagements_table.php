<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add daily_start_time / daily_end_time to engagements as templates.
 * When sessions are auto-generated, these values are copied to each session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->time('daily_start_time')->nullable()->after('days_of_week');
            $table->time('daily_end_time')->nullable()->after('daily_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropColumn(['daily_start_time', 'daily_end_time']);
        });
    }
};
