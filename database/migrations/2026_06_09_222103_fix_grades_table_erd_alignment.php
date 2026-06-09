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
        Schema::table('grades', function (Blueprint $table) {
            $table->renameColumn('user_id', 'student_id');
            $table->foreignId('graded_by')->nullable()->constrained('users');
            $table->foreignId('overridden_by')->nullable()->constrained('users');
            $table->decimal('original_value', 8, 2)->nullable();
            $table->text('override_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['graded_by']);
            $table->dropForeign(['overridden_by']);
            $table->dropColumn(['graded_by', 'overridden_by', 'original_value', 'override_note']);
            $table->renameColumn('student_id', 'user_id');
        });
    }
};
