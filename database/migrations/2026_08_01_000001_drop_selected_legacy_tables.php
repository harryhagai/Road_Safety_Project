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
        Schema::dropIfExists('trip_violations');
        Schema::dropIfExists('trip_telemetry');
        Schema::dropIfExists('vehicle_telemetry');
        Schema::dropIfExists('passenger_trips');
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('hotspots');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('evidence_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->binary('file_data')->nullable();
            $table->timestamps();
        });

        Schema::create('hotspots', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('radius_meters', 10, 2)->default(100);
            $table->unsignedInteger('frequency')->default(0);
            $table->string('severity', 30)->default('medium');
            $table->foreignId('segment_id')->nullable()->constrained('road_segments')->nullOnDelete();
            $table->foreignId('segment_type_rule_id')->nullable()->constrained('segment_type_rules')->nullOnDelete();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'frequency']);
        });
    }
};
