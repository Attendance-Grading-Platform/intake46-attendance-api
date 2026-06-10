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
        Schema::create('attendance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_ledger_id')->constrained('attendance_ledgers')->onDelete('cascade');
            $table->string('type'); 
            $table->integer('points');
            $table->string('description')->nullable();
            
            // SC-3: Unique constraint to prevent duplicate transactions for same student/session
            $table->foreignId('session_id')->nullable()->constrained('engagement_sessions')->onDelete('cascade');
            $table->foreignId('excuse_id')->nullable()->constrained('excuse_requests')->onDelete('set null');
            
            $table->timestamps();

            // Prevent duplicate entries for same session to protect point integrity
            $table->unique(['attendance_ledger_id', 'session_id'], 'unique_session_transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_transactions');
    }
};
