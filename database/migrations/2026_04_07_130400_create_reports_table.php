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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('violation_type_id')->constrained('violation_types')->restrictOnDelete();
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('location_name')->nullable();
            $table->string('status', 50)->default('submitted');
            $table->string('priority', 30)->default('normal');
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('officer_id')->nullable()->constrained('officers')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('officer_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
