<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous migration class for this database change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('road_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->string('rule_type', 100);
            $table->decimal('latitude_start', 10, 7)->nullable();
            $table->decimal('longitude_start', 10, 7)->nullable();
            $table->decimal('latitude_end', 10, 7)->nullable();
            $table->decimal('longitude_end', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->string('rule_value')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('segment_id')->nullable()->constrained('road_segments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('officers')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('road_rules');
    }
};
