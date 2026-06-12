<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cohort_id')->constrained()->onDelete('cascade');
            $table->boolean('at_risk')->default(false);
            $table->json('reasons')->nullable();
            $table->timestamp('flagged_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Scope unique check: one flag record per student per cohort
            $table->unique(['student_id', 'cohort_id']);
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
