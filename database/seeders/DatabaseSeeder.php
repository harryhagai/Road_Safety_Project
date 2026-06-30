<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Migration or seeding class that supports the DatabaseSeeder data layer.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ViolationTypeSeeder::class,
        ]);
    }
}
