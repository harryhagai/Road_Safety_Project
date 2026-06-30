<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertDontSee(route('hotspots.index'), false)
            ->assertSee(route('developer'), false)
            ->assertSee('Developers');

        $this->assertSame(1, substr_count($response->getContent(), route('developer')));
    }

    public function test_login_page_is_available_to_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_authenticated_user_is_redirected_from_login_to_officer_dashboard(): void
    {
        $officer = new User([
            'name' => 'Test Officer',
            'email' => 'officer@example.com',
            'password' => 'secret',
            'role' => User::ROLE_ROAD_OFFICER,
        ]);

        $response = $this->actingAs($officer)->get('/login');

        $response->assertRedirect(route('officer.dashboard'));
    }

    public function test_authenticated_officer_sees_officer_dashboard_in_the_public_header(): void
    {
        $officer = new User([
            'name' => 'Header Officer',
            'email' => 'header-officer@example.com',
            'password' => 'secret',
            'role' => User::ROLE_ROAD_OFFICER,
        ]);
        $officer->id = 7;

        $this->actingAs($officer)
            ->get('/')
            ->assertOk()
            ->assertSee(route('officer.dashboard'), false)
            ->assertSee('Dashboard');
    }
}
