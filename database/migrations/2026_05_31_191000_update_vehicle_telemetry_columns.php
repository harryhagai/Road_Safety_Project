<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_telemetry', 'citizen_device_no')) {
                $table->string('citizen_device_no', 60)->nullable()->after('telemetry_id');
            }
        });

        if (Schema::hasColumn('vehicle_telemetry', 'vehicle_reg_no')) {
            DB::table('vehicle_telemetry')
                ->whereNull('citizen_device_no')
                ->update(['citizen_device_no' => DB::raw('vehicle_reg_no')]);
        }

        DB::table('vehicle_telemetry')
            ->whereNull('citizen_device_no')
            ->update(['citizen_device_no' => 'UNKNOWN-DEVICE']);

        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            try {
                $table->dropIndex('vehicle_telemetry_vehicle_reg_no_index');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            if (Schema::hasColumn('vehicle_telemetry', 'vehicle_reg_no')) {
                $table->dropColumn('vehicle_reg_no');
            }

            try {
                $table->dropIndex('vehicle_telemetry_status_color_index');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            try {
                $table->dropIndex('vehicle_telemetry_segment_id_status_color_index');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            if (Schema::hasColumn('vehicle_telemetry', 'status_color')) {
                $table->dropColumn('status_color');
            }

            $table->index('citizen_device_no');
            $table->index(['segment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicle_telemetry', 'vehicle_reg_no')) {
                $table->string('vehicle_reg_no', 60)->nullable()->after('telemetry_id');
            }

            if (! Schema::hasColumn('vehicle_telemetry', 'status_color')) {
                $table->enum('status_color', ['green', 'blue', 'red'])->default('green')->after('current_speed');
            }
        });

        DB::table('vehicle_telemetry')
            ->whereNull('vehicle_reg_no')
            ->update(['vehicle_reg_no' => DB::raw('citizen_device_no')]);

        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            try {
                $table->dropIndex('vehicle_telemetry_citizen_device_no_index');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            try {
                $table->dropIndex('vehicle_telemetry_segment_id_created_at_index');
            } catch (\Throwable) {
                // Ignore when index does not exist.
            }

            if (Schema::hasColumn('vehicle_telemetry', 'citizen_device_no')) {
                $table->dropColumn('citizen_device_no');
            }

            $table->index('vehicle_reg_no');
            $table->index('status_color');
            $table->index(['segment_id', 'status_color']);
        });
    }
};
