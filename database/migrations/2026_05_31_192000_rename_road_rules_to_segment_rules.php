<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('road_rules', 'segment_rules');

        Schema::table('rule_violations', function (Blueprint $table): void {
            $table->dropForeign(['rule_id']);
            $table->foreign('rule_id')->references('id')->on('segment_rules')->cascadeOnDelete();
        });

        Schema::table('hotspots', function (Blueprint $table): void {
            $table->dropForeign(['rule_id']);
            $table->foreign('rule_id')->references('id')->on('segment_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotspots', function (Blueprint $table): void {
            $table->dropForeign(['rule_id']);
            $table->foreign('rule_id')->references('id')->on('road_rules')->nullOnDelete();
        });

        Schema::table('rule_violations', function (Blueprint $table): void {
            $table->dropForeign(['rule_id']);
            $table->foreign('rule_id')->references('id')->on('road_rules')->cascadeOnDelete();
        });

        Schema::rename('segment_rules', 'road_rules');
    }
};
