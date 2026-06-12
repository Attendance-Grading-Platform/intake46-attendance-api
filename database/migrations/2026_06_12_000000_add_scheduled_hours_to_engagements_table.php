<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (!Schema::hasColumn('engagements', 'scheduled_hours')) {
                $table->integer('scheduled_hours')->default(0)->after('hours_per_session');
            }
        });
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            if (Schema::hasColumn('engagements', 'scheduled_hours')) {
                $table->dropColumn('scheduled_hours');
            }
        });
    }
};
