<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ENG-3: Each engagement records type, assigned instructor, date range, and scheduled hours.
     * ENG-4: A person may hold multiple engagements across different tracks.
     */
    public function up(): void
    {
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->enum('type', ['lecture', 'lab', 'business']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours_per_session', 8, 2);
            $table->timestamps();

            $table->index(['instructor_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
    }
};
