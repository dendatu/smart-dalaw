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
        Schema::create('facility_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 16);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['facility_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_schedules');
    }
};
