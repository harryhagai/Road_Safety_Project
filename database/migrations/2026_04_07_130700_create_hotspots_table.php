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
        Schema::create('hotspots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('radius_meters', 10, 2)->default(100);
            $table->unsignedInteger('frequency')->default(0);
            $table->string('severity', 30)->default('medium');
            $table->foreignId('rule_id')->nullable()->constrained('road_rules')->nullOnDelete();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspots');
    }
};
