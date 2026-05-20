<?php

namespace Database\Seeders;

use App\Models\ViolationType;
use Illuminate\Database\Seeder;

/**
 * Migration or seeding class that supports the ViolationTypeSeeder data layer.
 */
class ViolationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        ViolationType::upsert(
            [
                [
                    'name' => 'Overspeeding',
                    'description' => 'Vehicle operating beyond the allowed speed limit.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Dangerous Overtaking',
                    'description' => 'Unsafe overtaking that puts other road users at risk.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Drunk Driving',
                    'description' => 'Suspected driving under the influence of alcohol or drugs.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Overloading',
                    'description' => 'Vehicle carrying passengers or goods beyond safe limits.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Road Damage',
                    'description' => 'Potholes, broken signage, or dangerous road infrastructure.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Traffic Obstruction',
                    'description' => 'Vehicles or activities blocking normal traffic flow.',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'is_active', 'updated_at']
        );
    }
}
