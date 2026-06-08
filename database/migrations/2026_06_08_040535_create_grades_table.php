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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            // foreign key safely linked to users table (student)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // foreign key linked to course_components table
            $table->foreignId('course_component_id')->constrained()->onDelete('cascade');

            $table->float('raw_score');
            $table->float('raw_max');
            $table->float('weight');
            $table->float('normalized_score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
