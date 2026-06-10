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
        Schema::create('student_risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('cohort_id')
                    ->constrained('cohorts')
                    ->cascadeOnDelete();
            $table->boolean('at_risk')->default(false);
            $table->json('reasons')->nullable(); // Store reasons for being at risk
            $table->timestamp('flagged_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_risk_flags');
    }
};
