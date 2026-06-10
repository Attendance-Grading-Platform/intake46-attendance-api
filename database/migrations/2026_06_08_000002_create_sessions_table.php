<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessions represent individual scheduled dates within an engagement.
     * BIL-1: delivered flag drives billable-hour calculation.
     */
    public function up(): void
    {
        Schema::create('engagement_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->date('session_date');
            $table->boolean('delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Prevent duplicate sessions for the same engagement on the same day
            $table->unique(['engagement_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_sessions');
    }
};
