<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->string('purpose')->default('password_reset')->after('name')->index();
        });

        DB::table('mail_settings')
            ->whereNull('purpose')
            ->update(['purpose' => 'password_reset']);
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
