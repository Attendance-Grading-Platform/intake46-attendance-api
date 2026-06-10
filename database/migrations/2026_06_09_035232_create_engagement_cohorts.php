<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('engagement_cohorts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('cohort_id')
                  ->constrained('cohorts')
                  ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['engagement_id', 'cohort_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagement_cohorts');
    }
};
