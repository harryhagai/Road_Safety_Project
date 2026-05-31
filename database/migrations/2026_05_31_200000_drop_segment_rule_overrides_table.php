<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('segment_rule_overrides');
    }

    public function down(): void
    {
        if (! Schema::hasTable('segment_rule_overrides')) {
            Schema::create('segment_rule_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('segment_id')->constrained('road_segments')->cascadeOnDelete();
                $table->foreignId('segment_type_rule_id')->constrained('segment_type_rules')->cascadeOnDelete();
                $table->string('rule_value')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->nullable();
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_to')->nullable();
                $table->timestamps();

                $table->unique(['segment_id', 'segment_type_rule_id'], 'segment_rule_override_unique');
                $table->index(['segment_id', 'effective_from', 'effective_to'], 'segment_rule_override_window_idx');
            });
        }
    }
};
