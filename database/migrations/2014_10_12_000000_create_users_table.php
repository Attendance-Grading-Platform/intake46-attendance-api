<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Laravel requires this column to be named 'password', not 'password_hash'
            $table->string('password');

            // ── Contextual RBAC ──────────────────────────────────────────
            // Indexed because we will constantly filter "Where role = student"
            $table->enum('role', [
                'branch_manager',
                'track_admin',
                'instructor',
                'student'
            ])->index();

            $table->date('expiry_date');
            $table->boolean('is_active')->default(true);

            // ── Billing & Accounting ─────────────────────────────────────
            $table->enum('compensation_type', ['internal', 'external'])->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('fixed_salary', 10, 2)->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Soft Deletes: Never hard-delete a user, it breaks the grading ledger
            $table->softDeletes();
        });

        // Keep default Laravel password_reset_tokens table
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Keep default Laravel sessions table (useful for stateful SPA fallback)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
