<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverAccessTest extends TestCase
{
    public function test_driver_authentication_pages_are_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('name="account_type"', false)
            ->assertDontSee('Account type')
            ->assertSee('identify your account automatically')
            ->assertSee(route('driver.register'), false);

        $this->get('/driver/login')
            ->assertRedirect(route('login'));

        $this->get('/driver/register')
            ->assertOk()
            ->assertSee('Driver full name')
            ->assertSee('Vehicle name')
            ->assertSee('Plate number')
            ->assertSee('Organization');
    }

    public function test_passenger_can_evaluate_but_cannot_use_driver_report_submission(): void
    {
        $payload = [
            'latitude' => -6.7924,
            'longitude' => 39.2083,
            'speed_kmh' => 60,
            'accuracy' => 100,
        ];

        $this->postJson('/auto-speed-reports/evaluate', $payload)
            ->assertOk()
            ->assertJsonPath('reason', 'low_accuracy');

        $this->postJson('/auto-speed-reports', $payload)->assertUnauthorized();
    }

    public function test_passenger_report_form_requires_a_pending_detected_violation(): void
    {
        $this->get('/passenger/report')
            ->assertRedirect(route('home'));

        $pending = [
            'token' => str_repeat('a', 40),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'violation_type' => 'Overspeeding',
            'violation_description' => 'Vehicle operating beyond the allowed speed limit.',
            'description' => 'Passenger-observed overspeeding.',
            'latitude' => -6.7924,
            'longitude' => 39.2083,
            'location_name' => 'Test segment',
            'priority' => 'medium',
            'segment_id' => 1,
            'rule_id' => 1,
            'rule_name' => 'Speed Limit',
            'rule_type' => 'speed_limit',
            'rule_value' => '50 km/h',
            'rule_description' => 'Test speed limit.',
            'confidence_score' => 95,
        ];

        $this->withSession(['passenger.pending_violation' => $pending])
            ->get('/passenger/report')
            ->assertOk()
            ->assertSee('Bus operator / company')
            ->assertSee('Bus plate number')
            ->assertDontSee('Fleet / side number')
            ->assertSee('Start camera')
            ->assertSee('Required')
            ->assertSee('Optional')
            ->assertSee('saved inside the database');
    }

    public function test_passenger_report_requires_bus_identity_and_captured_image(): void
    {
        $pending = [
            'token' => str_repeat('b', 40),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $this->withSession(['passenger.pending_violation' => $pending])
            ->post('/passenger/report', [
                'pending_token' => str_repeat('b', 40),
            ])
            ->assertSessionHasErrors([
                'bus_operator',
                'bus_plate_number',
                'evidence_image',
            ]);
    }

    public function test_authenticated_driver_home_enables_identified_reporting_configuration(): void
    {
        $driver = new User([
            'name' => 'Test Driver',
            'email' => 'driver@example.com',
            'vehicle_name' => 'Toyota Hiace',
            'plate_number' => 'T 123 ABC',
            'organization' => 'Test Transport',
            'password' => 'password',
            'role' => User::ROLE_DRIVER,
        ]);
        $driver->id = 42;

        $this->actingAs($driver)
            ->get('/')
            ->assertOk()
            ->assertSee('authenticated: true', false)
            ->assertSee('driverId: 42', false);
    }

    public function test_driver_dashboard_requires_login_and_only_queries_the_logged_in_driver_reports(): void
    {
        $this->get('/driver/dashboard')
            ->assertRedirect(route('login'));

        $driver = new User([
            'name' => 'Dashboard Driver',
            'email' => 'dashboard-driver@example.com',
            'vehicle_name' => 'Toyota Hiace',
            'plate_number' => 'T 456 XYZ',
            'organization' => 'Dashboard Transport',
            'password' => 'password',
            'role' => User::ROLE_DRIVER,
        ]);
        $driver->id = 42;

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'from `reports`')) {
                $queries[] = $query->bindings;
            }
        });

        $this->actingAs($driver)
            ->get('/driver/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Dashboard Driver')
            ->assertSee('T 456 XYZ')
            ->assertSee('Reports linked to your driver ID');

        $this->assertNotEmpty($queries);
        $this->assertTrue(collect($queries)->every(
            fn (array $bindings): bool => in_array(42, $bindings, true)
        ));
    }

    public function test_authenticated_driver_sees_dashboard_in_the_public_header(): void
    {
        $driver = new User([
            'name' => 'Header Driver',
            'email' => 'header-driver@example.com',
            'vehicle_name' => 'Isuzu N-Series',
            'plate_number' => 'T 789 HDR',
            'organization' => 'Header Transport',
            'password' => 'password',
            'role' => User::ROLE_DRIVER,
        ]);
        $driver->id = 43;

        $this->actingAs($driver)
            ->get('/')
            ->assertOk()
            ->assertSee(route('driver.dashboard'), false)
            ->assertSee('Dashboard')
            ->assertDontSee('> Login</a>', false);

        $this->actingAs($driver)
            ->get('/login')
            ->assertRedirect(route('driver.dashboard'));
    }

    public function test_guest_home_keeps_the_original_map_workspace_without_driver_banner(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="mainPublicMap"', false)
            ->assertDontSee('Driver login required for reporting.');
    }

    public function test_roles_cannot_cross_into_another_workspace(): void
    {
        $driver = new User([
            'name' => 'Restricted Driver',
            'email' => 'restricted-driver@example.com',
            'role' => User::ROLE_DRIVER,
            'password' => 'password',
        ]);
        $driver->id = 71;

        $roadOfficer = new User([
            'name' => 'Restricted Officer',
            'email' => 'restricted-officer@example.com',
            'role' => User::ROLE_ROAD_OFFICER,
            'password' => 'password',
        ]);
        $roadOfficer->id = 72;

        $passenger = new User([
            'name' => 'Registered Passenger',
            'email' => 'passenger@example.com',
            'role' => User::ROLE_PASSENGER,
            'password' => 'password',
        ]);
        $passenger->id = 73;

        $this->actingAs($driver)
            ->get('/road-officer/dashboard')
            ->assertForbidden();

        $this->actingAs($roadOfficer)
            ->get('/driver/dashboard')
            ->assertForbidden();

        $this->actingAs($passenger)
            ->get('/road-officer/dashboard')
            ->assertForbidden();

        $this->actingAs($passenger)
            ->get('/driver/dashboard')
            ->assertForbidden();
    }

    public function test_registered_passenger_is_recognized_in_the_public_header(): void
    {
        $passenger = new User([
            'name' => 'Registered Passenger',
            'email' => 'registered-passenger@example.com',
            'role' => User::ROLE_PASSENGER,
            'password' => 'password',
        ]);
        $passenger->id = 74;

        $this->actingAs($passenger)
            ->get('/')
            ->assertOk()
            ->assertSee('Passenger')
            ->assertDontSee('> Login</a>', false);
    }
}
