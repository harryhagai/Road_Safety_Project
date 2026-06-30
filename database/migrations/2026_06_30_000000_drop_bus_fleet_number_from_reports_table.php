<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reports', 'bus_fleet_number')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn('bus_fleet_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reports', 'bus_fleet_number')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('bus_fleet_number', 100)->nullable()->after('bus_route');
        });
    }
};
