<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passenger_trips', function (Blueprint $table): void {
            $table->id();
            $table->string('public_reference', 40)->unique();
            $table->string('device_id', 120)->index();
            $table->string('route_name')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('trip_telemetry', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained('passenger_trips')->cascadeOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 7, 2)->default(0);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('network_type', 40)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'recorded_at']);
        });

        Schema::create('trip_violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained('passenger_trips')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('type', 80);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at')->index();
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_violations');
        Schema::dropIfExists('trip_telemetry');
        Schema::dropIfExists('passenger_trips');
    }
};
