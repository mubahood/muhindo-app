<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The public site must reflect the signed-in state on every page: a role-aware
 * account link (student → My Courses, admin → Dashboard, client → My Projects)
 * plus Sign out — never a stale "Sign in" for an authenticated visitor. And a
 * session must survive until explicit sign-out (always-on remember cookie).
 */
class PublicHeaderAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_sign_in_and_no_sign_out(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('Sign in')->assertDontSee('Sign out');
    }

    public function test_a_signed_in_student_sees_my_courses_and_sign_out_not_sign_in(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/');

        $response->assertOk();
        $response->assertSee('My Courses');
        $response->assertSee(route('learn.index'), false);
        $response->assertSee('Sign out');
        $response->assertDontSee('>Sign in<', false);
    }

    public function test_a_signed_in_admin_sees_the_dashboard_link(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk()->assertSee('Dashboard')->assertSee(route('dashboard'), false);
    }

    public function test_a_signed_in_client_sees_my_projects(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->get('/');

        $response->assertOk()->assertSee('My Projects')->assertSee(route('portal.index'), false);
    }

    public function test_the_auth_state_shows_on_the_e_learning_pages_too(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/e-learning');

        $response->assertOk()->assertSee('My Courses')->assertSee('Sign out');
    }

    public function test_logging_in_always_issues_the_long_lived_remember_cookie(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => Hash::make('password123')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
            // deliberately no "remember" field — remembering is the default policy
        ]);

        $response->assertRedirect();
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_registering_also_issues_the_remember_cookie(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Sticky Session',
            'email' => 'sticky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertRedirect();
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_signing_out_from_the_public_header_ends_the_session(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
