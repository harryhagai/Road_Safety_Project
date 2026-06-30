<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UnifiedUserRolesTest extends TestCase
{
    public function test_all_account_roles_use_the_single_users_auth_provider(): void
    {
        $this->assertSame('users', config('auth.guards.web.provider'));
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertArrayNotHasKey('driver', config('auth.guards'));
        $this->assertArrayNotHasKey('officers', config('auth.providers'));
    }

    public function test_each_role_resolves_to_its_correct_destination(): void
    {
        $passenger = new User(['role' => User::ROLE_PASSENGER]);
        $driver = new User(['role' => User::ROLE_DRIVER]);
        $roadOfficer = new User(['role' => User::ROLE_ROAD_OFFICER]);
        $admin = new User(['role' => User::ROLE_ADMIN]);

        $this->assertSame('home', $passenger->dashboardRouteName());
        $this->assertSame('driver.dashboard', $driver->dashboardRouteName());
        $this->assertSame('officer.dashboard', $roadOfficer->dashboardRouteName());
        $this->assertSame('officer.dashboard', $admin->dashboardRouteName());
        $this->assertTrue($roadOfficer->canAccessOfficerWorkspace());
        $this->assertTrue($admin->canAccessOfficerWorkspace());
        $this->assertFalse($driver->canAccessOfficerWorkspace());
    }

    public function test_supported_roles_are_explicit_and_stable(): void
    {
        $this->assertSame([
            'passenger',
            'driver',
            'road_officer',
            'admin',
        ], User::ROLES);
    }
}
