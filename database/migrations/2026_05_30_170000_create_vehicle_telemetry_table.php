<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_telemetry', function (Blueprint $table): void {
            $table->id('telemetry_id');
            $table->string('vehicle_reg_no', 60)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('current_speed', 7, 2)->default(0);
            $table->enum('status_color', ['green', 'blue', 'red'])->index();
            $table->foreignId('segment_id')->nullable()->constrained('road_segments')->nullOnDelete();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['segment_id', 'status_color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_telemetry');
    }
};

