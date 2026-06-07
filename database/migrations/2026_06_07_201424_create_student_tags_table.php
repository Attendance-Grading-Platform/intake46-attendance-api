<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_tags', function (Blueprint $table) {
            $table->id();
            //student how got tagged
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // admin who gave the tag
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('tag');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tags');
    }
};