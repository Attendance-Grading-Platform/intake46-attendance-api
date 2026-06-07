<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();

            $table->string('period');
            $table->enum('compensation_type', ['internal', 'external']);

            $table->decimal('delivered_hours', 8, 2)->default(0);
            $table->decimal('fixed_salary_component', 10, 2)->default(0);
            $table->decimal('hourly_component', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_snapshots');
    }
};