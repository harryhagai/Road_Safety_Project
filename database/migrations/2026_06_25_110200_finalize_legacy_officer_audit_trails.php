<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_trails')) {
            return;
        }

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

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }
    }

    public function down(): void
    {
        // Historical model aliases are intentionally not restored.
    }
};
