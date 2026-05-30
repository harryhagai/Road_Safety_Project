<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            $table->decimal('heading', 6, 2)->nullable()->after('current_speed');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_telemetry', function (Blueprint $table): void {
            $table->dropColumn('heading');
        });
    }
};

