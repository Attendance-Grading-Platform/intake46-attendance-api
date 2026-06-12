<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ATT-4: Every student's ledger starts at 250 points.
     * ATT-6: One single ledger per student spanning the whole program.
     */
    public function up(): void
    {
        Schema::create('attendance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->unique()               // One ledger per student
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('cohort_id')
                  ->constrained('cohorts')
                  ->cascadeOnDelete();
            $table->integer('balance')->default(250);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_ledgers');
    }
};
