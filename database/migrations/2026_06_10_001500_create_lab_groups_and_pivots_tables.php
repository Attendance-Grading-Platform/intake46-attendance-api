<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD Tables: LAB_GROUPS, LAB_GROUP_STUDENTS, LAB_GROUP_INSTRUCTORS
     *
     * A cohort of ~45 students splits into 2–3 lab groups of ~15.
     * Each group's instructor grades only their own students (GRD-4, ACC-3).
     */
    public function up(): void
    {
        Schema::create('lab_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')
                  ->constrained('cohorts')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('lab_group_students', function (Blueprint $table) {
            $table->foreignId('lab_group_id')
                  ->constrained('lab_groups')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->primary(['lab_group_id', 'user_id']);
        });

        Schema::create('lab_group_instructors', function (Blueprint $table) {
            $table->foreignId('lab_group_id')
                  ->constrained('lab_groups')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->primary(['lab_group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_group_instructors');
        Schema::dropIfExists('lab_group_students');
        Schema::dropIfExists('lab_groups');
    }
};
