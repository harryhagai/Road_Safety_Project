<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE evidence_files
            MODIFY file_path VARCHAR(191) NULL,
            ADD file_data MEDIUMBLOB NULL AFTER file_path
        ');
    }

    public function down(): void
    {
        DB::table('evidence_files')
            ->whereNull('file_path')
            ->update(['file_path' => DB::raw("CONCAT('database://', id)")]);

        DB::statement('
            ALTER TABLE evidence_files
            DROP COLUMN file_data,
            MODIFY file_path VARCHAR(191) NOT NULL
        ');
    }
};
