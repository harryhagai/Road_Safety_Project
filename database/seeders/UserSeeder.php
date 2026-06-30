<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'hngobey@gmail.com'],
            [
                'name' => 'Hagai Road Officer',
                'password' => 'rsrs@44242444!',
                'role' => User::ROLE_ROAD_OFFICER,
            ]
        );
    }
}
