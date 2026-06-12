<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ENG-3 / ERD Compliance:
     * Many-to-many pivot between Engagements and Cohorts.
     * An engagement can cover multiple cohorts, and a cohort can have
     * multiple engagements across its lifecycle.
     */
    public function up(): void
    {
        Schema::create('engagement_cohorts', function (Blueprint $table) {
            $table->foreignId('engagement_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('cohort_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->primary(['engagement_id', 'cohort_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_cohorts');
    }
};
