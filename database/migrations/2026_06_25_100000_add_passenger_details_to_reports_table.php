<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('reporter_type', 30)->default('legacy')->after('driver_id');
            $table->string('bus_operator')->nullable()->after('reporter_type');
            $table->string('bus_plate_number', 50)->nullable()->after('bus_operator');
            $table->string('bus_route')->nullable()->after('bus_plate_number');
            $table->string('passenger_name')->nullable()->after('bus_route');
            $table->string('passenger_phone', 50)->nullable()->after('passenger_name');
            $table->text('passenger_notes')->nullable()->after('passenger_phone');

            $table->index(['reporter_type', 'created_at']);
            $table->index('bus_plate_number');
        });

        DB::table('reports')
            ->whereNotNull('driver_id')
            ->update(['reporter_type' => 'driver']);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['reporter_type', 'created_at']);
            $table->dropIndex(['bus_plate_number']);
            $table->dropColumn([
                'reporter_type',
                'bus_operator',
                'bus_plate_number',
                'bus_route',
                'passenger_name',
                'passenger_phone',
                'passenger_notes',
            ]);
        });
    }
};
