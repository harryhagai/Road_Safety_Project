<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('vehicle_name')->nullable()->after('email_verified_at');
            $table->string('plate_number', 50)->nullable()->unique()->after('vehicle_name');
            $table->string('organization')->nullable()->after('plate_number');
            $table->timestamp('last_login_at')->nullable()->after('organization');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['plate_number']);
            $table->dropColumn([
                'vehicle_name',
                'plate_number',
                'organization',
                'last_login_at',
            ]);
        });
    }
};
