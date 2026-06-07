<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: cohort_track_admins
 * Implements the COHORT_TRACK_ADMINS entity from the ERD.
 * Links one or more Track Admins to a cohort (LC-2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_track_admins', function (Blueprint $table) {
            $table->foreignId('cohort_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->primary(['cohort_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_track_admins');
    }
};
