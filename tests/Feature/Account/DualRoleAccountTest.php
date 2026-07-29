<?php

namespace Tests\Feature\Account;

use App\Models\Client;
use App\Models\Course;
use App\Models\Project;
use App\Models\ProjectInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Account capabilities: one person can learn AND hire. Registration asks which,
 * the dashboard composes the matching sections, the sidebar shows the matching
 * menu, and the profile can add a capability later.
 */
class DualRoleAccountTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $accountType, string $email = 'new@example.com'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('register'), [
            'name' => 'New Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'account_type' => $accountType,
        ]);
    }

    public function test_registering_as_a_student_grants_only_learning_access(): void
    {
        $this->register('student');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertFalse($user->isClient());
        $this->assertSame(['student'], $user->accountTypes());
        $this->assertNull($user->client);
    }

    public function test_registering_as_a_client_grants_client_access_and_creates_a_client_profile(): void
    {
        $response = $this->register('client');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->isClient());
        $this->assertFalse($user->isStudent());
        // A client needs a client record to own projects and be invoiced against.
        $this->assertNotNull($user->client);
        $this->assertSame('New Person', $user->client->name);
        $response->assertRedirect(route('portal.index'));
    }

    public function test_registering_as_both_grants_both_and_lands_on_the_dashboard(): void
    {
        $response = $this->register('both');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertTrue($user->isClient());
        $this->assertSame(['student', 'client'], $user->accountTypes());
        $this->assertNotNull($user->client);
        $this->assertSame('Student & Client', $user->accountTypeLabel());
        $response->assertRedirect(route('dashboard'));
    }

    public function test_the_account_type_is_required(): void
    {
        $this->post(route('register'), [
            'name' => 'No Type', 'email' => 'notype@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
        ])->assertSessionHasErrors('account_type');
    }

    public function test_the_register_form_preselects_client_when_coming_from_start_a_project(): void
    {
        $response = $this->get(route('register'), ['HTTP_REFERER' => route('start-a-project')]);

        $response->assertOk();
        // The selected radio is the one Alpine initialises `type` to.
        $response->assertSee("{ type: 'client' }", false);
    }

    public function test_the_register_form_preselects_student_for_a_course_context(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $this->get(route('register', ['intended_course' => $course->slug]))
            ->assertOk()->assertSee("{ type: 'student' }", false);
    }

    public function test_an_explicit_account_type_query_param_wins(): void
    {
        $this->get(route('register', ['account_type' => 'both']))
            ->assertOk()->assertSee("{ type: 'both' }", false);
    }

    public function test_a_dual_role_dashboard_shows_both_learning_and_project_sections(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true, 'is_client' => true]);
        Client::create([
            'uuid' => (string) Str::uuid(), 'client_number' => 'CL-TEST-1',
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Enrolled courses');   // student section
        $response->assertSee('Active projects');    // client section
    }

    public function test_a_student_only_dashboard_has_no_project_section(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('Enrolled courses')->assertDontSee('Active projects');
    }

    public function test_the_sidebar_shows_the_menu_matching_the_capabilities(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $response = $this->actingAs($student)->get(route('dashboard'));
        $response->assertSee('Learning')->assertSee('My Courses')->assertDontSee('Invoices');

        $both = User::factory()->create(['role' => 'student', 'is_student' => true, 'is_client' => true]);
        $response = $this->actingAs($both)->get(route('dashboard'));
        $response->assertSee('Learning')->assertSee('Invoices')->assertSee('Start a project');
    }

    public function test_a_user_can_add_client_access_from_their_profile(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($user)->postJson(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'account_type' => 'both',
        ])->assertOk()->assertJsonPath('account_type_label', 'Student & Client');

        $user->refresh();
        $this->assertTrue($user->isClient());
        $this->assertNotNull($user->client, 'granting client access should create the client profile');
    }

    public function test_dropping_a_capability_never_orphans_existing_work(): void
    {
        // A client with a real project asks to become student-only: client access
        // is retained rather than orphaning the project and its invoices.
        $user = User::factory()->create(['role' => 'client', 'is_client' => true]);
        $client = Client::create([
            'uuid' => (string) Str::uuid(), 'client_number' => 'CL-TEST-2',
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ]);
        Project::create([
            'uuid' => (string) Str::uuid(), 'project_number' => 'PRJ-TEST-1', 'title' => 'Live project',
            'client_id' => $client->id, 'status' => 'active',
        ]);

        $this->actingAs($user)->postJson(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'account_type' => 'student',
        ])->assertOk();

        $this->assertTrue($user->refresh()->isClient());
    }

    public function test_a_signed_in_clients_project_request_is_linked_to_their_account(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_client' => true]);

        $this->actingAs($user)->post(route('start-a-project.store'), [
            'name' => $user->name, 'email' => $user->email,
            'project_type' => 'website',
            'description' => 'A marketing site for my shop.',
        ])->assertRedirect();

        $inquiry = ProjectInquiry::where('email', $user->email)->firstOrFail();
        $this->assertSame($user->id, $inquiry->user_id);
    }

    public function test_the_client_dashboard_shows_their_open_request(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_client' => true]);
        ProjectInquiry::factory()->create([
            'user_id' => $user->id, 'project_type' => 'mobile_app', 'status' => 'new',
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('Your requests')->assertSee('Mobile app');
    }

    public function test_a_guest_request_stays_unlinked(): void
    {
        $this->post(route('start-a-project.store'), [
            'name' => 'Guest', 'email' => 'guest@example.com',
            'project_type' => 'website', 'description' => 'Something small.',
        ])->assertRedirect();

        $this->assertNull(ProjectInquiry::where('email', 'guest@example.com')->firstOrFail()->user_id);
    }

    public function test_an_admin_implicitly_reaches_both_surfaces(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->assertTrue($admin->isStudent());
        $this->assertTrue($admin->isClient());
        // ...but the label still reads as their real role, not "Student & Client".
        $this->assertSame('Owner', $admin->accountTypeLabel());
    }
}
