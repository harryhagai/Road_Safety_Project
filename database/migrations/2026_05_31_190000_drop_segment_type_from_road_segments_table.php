<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('road_segments', 'segment_type')) {
            Schema::table('road_segments', function (Blueprint $table) {
                $table->dropColumn('segment_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('road_segments', 'segment_type')) {
            Schema::table('road_segments', function (Blueprint $table) {
                $table->string('segment_type', 100)->nullable()->after('segment_name');
            });
        }
    }
};
