<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_trails') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['road_officer', 'admin'])
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $user): void {
                DB::table('audit_trails')
                    ->where('actor_type', 'App\\Models\\Officer')
                    ->where('actor_name', $user->name)
                    ->update([
                        'actor_type' => 'App\\Models\\User',
                        'actor_id' => $user->id,
                    ]);

                DB::table('audit_trails')
                    ->where('subject_type', 'App\\Models\\Officer')
                    ->where('subject_name', $user->name)
                    ->update([
                        'subject_type' => 'App\\Models\\User',
                        'subject_id' => $user->id,
                    ]);
            });

        DB::table('audit_trails')
            ->where('actor_type', 'App\\Models\\Officer')
            ->update([
                'actor_type' => 'App\\Models\\User',
                'actor_id' => null,
            ]);

        DB::table('audit_trails')
            ->where('subject_type', 'App\\Models\\Officer')
            ->update([
                'subject_type' => 'App\\Models\\User',
                'subject_id' => null,
            ]);
    }

    public function down(): void
    {
        // Historical audit identities intentionally remain normalized to users.
    }
};
