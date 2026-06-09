<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });

        DB::statement("
            CREATE UNIQUE INDEX cohorts_one_active_per_track
            ON cohorts (track_id)
            WHERE status = 'active'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cohorts_one_active_per_track');
        Schema::dropIfExists('cohorts');
    }
};
