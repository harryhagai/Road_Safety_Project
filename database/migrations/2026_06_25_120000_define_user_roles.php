<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLES = [
        'passenger',
        'driver',
        'road_officer',
        'admin',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->whereNull('role')
            ->orWhere('role', '')
            ->update(['role' => 'passenger']);

        DB::table('users')
            ->where('role', 'officer')
            ->update(['role' => 'road_officer']);

        DB::table('users')
            ->where('role', 'hgadmin')
            ->update(['role' => 'admin']);

        $invalidRoles = DB::table('users')
            ->whereNotIn('role', self::ROLES)
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->all();

        if ($invalidRoles !== []) {
            throw new \RuntimeException(
                'Cannot define users.role because unsupported roles exist: '.implode(', ', $invalidRoles)
            );
        }

        // Correct the officer account that was previously seeded with admin privileges.
        DB::table('users')
            ->where('email', 'hngobey@gmail.com')
            ->where('role', 'admin')
            ->update(['role' => 'road_officer']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', self::ROLES)
                ->default('passenger')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)
                ->default('passenger')
                ->change();
        });
    }
};
