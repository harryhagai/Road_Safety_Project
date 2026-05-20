<?php

namespace Database\Seeders;

use App\Models\Officer;
use Illuminate\Database\Seeder;

/**
 * Migration or seeding class that supports the OfficerSeeder data layer.
 */
class OfficerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Officer::updateOrCreate(
            ['email' => 'hngobey@gmail.com'],
            [
                'full_name' => 'Hagai Road Officer',
                'password' => 'rsrs@44242444!',
                'role' => 'admin',
            ]
        );
    }
}
