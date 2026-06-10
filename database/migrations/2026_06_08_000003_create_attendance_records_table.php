<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ATT-1: Record arrived_at (scan-in) and left_at (scan-out) timestamps.
     * ATT-3: Business-session attendance recorded per track — session already
     *        belongs to an engagement scoped to a cohort/track, so the FK chain
     *        preserves per-track isolation.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                  ->constrained('engagement_sessions')
                  ->cascadeOnDelete();
            $table->foreignId('student_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('track_id')
                  ->constrained('tracks')
                  ->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'excused'])->default('absent');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // One attendance record per student per session
            $table->unique(['session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
