<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_type_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_type_id')->constrained('segment_types')->cascadeOnDelete();
            $table->string('rule_name');
            $table->string('rule_type', 100);
            $table->string('rule_value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_type_rules');
    }
};
