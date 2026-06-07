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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); // Laravel convention, mapped to password_hash in ERD
            $table->enum('role', ['branch_manager', 'track_admin', 'instructor', 'student']);
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('compensation_type', ['internal', 'external'])->default('internal');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('fixed_salary', 10, 2)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
