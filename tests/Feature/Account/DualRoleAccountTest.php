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
        return $this->post(route('register'), $this->shielded([
            'name' => 'New Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'account_type' => $accountType,
        ]));
    }

    /** Exactly one account-type radio is checked, and it is the expected one. */
    private function assertRadioChecked(string $html, string $expected): void
    {
        preg_match_all('/<input[^>]*name="account_type"[^>]*>/', $html, $inputs);

        $checked = array_values(array_filter(
            $inputs[0],
            fn (string $input) => str_contains($input, 'checked')
        ));

        $this->assertCount(1, $checked, 'exactly one account type must be preselected');
        $this->assertStringContainsString('value="'.$expected.'"', $checked[0]);
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
        /* Straight to the proposal, not the portal. They pressed "Hire Me" a
           minute ago, and the portal has nothing in it yet. */
        $response->assertRedirect(route('propose'));
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
        // Both roles, but still a client who has not said what they want built.
        $response->assertRedirect(route('propose'));
    }

    public function test_the_account_type_is_required(): void
    {
        $this->post(route('register'), $this->shielded([
            'name' => 'No Type', 'email' => 'notype@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
        ]))->assertSessionHasErrors('account_type');
    }

    public function test_the_register_form_preselects_client_when_coming_from_start_a_project(): void
    {
        $response = $this->get(route('register'), ['HTTP_REFERER' => route('hire')]);

        $response->assertOk();
        /* Assert the radio itself is checked. The previous version looked for
           an Alpine attribute, which was present in the HTML but never ran,
           because the auth layout loads no Alpine. It passed for months while
           no option was ever actually selected. */
        $this->assertRadioChecked($response->getContent(), 'client');
    }

    public function test_the_register_form_preselects_student_for_a_course_context(): void
    {
        $course = Course::factory()->create(['is_published' => true]);

        $this->assertRadioChecked(
            $this->get(route('register', ['intended_course' => $course->slug]))->assertOk()->getContent(),
            'student'
        );
    }

    public function test_an_explicit_account_type_query_param_wins(): void
    {
        $this->assertRadioChecked(
            $this->get(route('register', ['account_type' => 'both']))->assertOk()->getContent(),
            'both'
        );
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

    public function test_a_user_can_add_client_access_from_their_account_page(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($user)->post(route('account.type'), ['account_type' => 'both'])
            ->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->isClient());
        $this->assertTrue($user->isStudent());
        $this->assertSame('Student & Client', $user->accountTypeLabel());
        $this->assertNotNull($user->client, 'granting client access should create the client profile');
    }

    public function test_the_account_page_shows_every_panel_a_student_can_act_on(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($user)->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Your details')
            ->assertSee('Account type')
            ->assertSee('Security')
            // Every input is reachable by its label, not by placeholder alone.
            ->assertSee('for="name"', false)
            ->assertSee('for="current_password"', false);
    }

    public function test_an_admin_is_not_offered_an_account_type_to_change(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('account.edit'))
            ->assertOk()->assertSee('Your details')->assertDontSee('Account type');
    }

    public function test_a_user_can_update_their_details(): void
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($user)->post(route('account.update'), [
            'name' => 'Renamed Person', 'email' => 'renamed@example.com', 'phone' => '+256700000000',
        ])->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('+256700000000', $user->phone);
    }

    public function test_details_errors_land_in_their_own_bag_so_other_panels_stay_clean(): void
    {
        $taken = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($user)->post(route('account.update'), [
            'name' => 'Someone', 'email' => $taken->email,
        ])->assertSessionHasErrorsIn('profile', ['email'])
            ->assertSessionDoesntHaveErrors(['current_password', 'password']);

        $this->assertNotSame($taken->email, $user->refresh()->email);
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

        $this->actingAs($user)->post(route('account.type'), ['account_type' => 'student'])
            ->assertRedirect()
            // ...and they're told why, rather than silently getting something else.
            ->assertSessionHas('warning');

        $this->assertTrue($user->refresh()->isClient());
    }

    public function test_an_unused_client_profile_does_not_trap_you_in_client_access(): void
    {
        // Choosing "both" creates an empty client profile up front. That container
        // is not work, so changing your mind afterwards has to actually take.
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $this->actingAs($user)->post(route('account.type'), ['account_type' => 'both'])->assertRedirect();
        $this->assertTrue($user->refresh()->isClient());

        $this->actingAs($user)->post(route('account.type'), ['account_type' => 'student'])
            ->assertRedirect()->assertSessionHas('success')->assertSessionMissing('warning');

        $this->assertFalse($user->refresh()->isClient());
        $this->assertSame(['student'], $user->accountTypes());
    }

    public function test_an_outstanding_invoice_also_holds_client_access_open(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_client' => true]);
        $client = Client::create([
            'uuid' => (string) Str::uuid(), 'client_number' => 'CL-TEST-3',
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ]);
        \App\Models\Invoice::create([
            'uuid' => (string) Str::uuid(), 'invoice_no' => 'INV-TEST-1',
            'billable_type' => Client::class, 'billable_id' => $client->id,
            'currency' => 'UGX', 'subtotal' => '100.00', 'total' => '100.00', 'balance' => '100.00',
            'status' => \App\Enums\InvoiceStatus::Issued->value,
        ]);

        $this->actingAs($user)->post(route('account.type'), ['account_type' => 'student'])
            ->assertRedirect()->assertSessionHas('warning');

        $this->assertTrue($user->refresh()->isClient());
    }

    public function test_a_signed_in_clients_project_request_is_linked_to_their_account(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_client' => true]);

        $this->actingAs($user)->post(route('propose.store'), [
            'title' => 'Marketing site',
            'category' => 'website',
            'description' => 'A marketing site for my shop, with a page per product and a contact number.',
            'timeline' => 'asap',
            'budget_currency' => 'UGX',
            'phone' => '+256700000000',
            'country' => 'Uganda',
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

    /**
     * There is no such thing as a guest request any more. A proposal needs an
     * account, so every one of them has an owner who can come back to it,
     * which is the whole reason the public lead form was retired.
     */
    public function test_a_guest_cannot_leave_an_unowned_request(): void
    {
        $this->post(route('propose.store'), [
            'title' => 'Something small', 'category' => 'website',
            'description' => 'Something small but described at enough length to be priced.',
            'timeline' => 'asap', 'budget_currency' => 'UGX', 'phone' => '0700000000',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, ProjectInquiry::count());
    }

    public function test_an_admin_implicitly_reaches_both_surfaces(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->assertTrue($admin->isStudent());
        $this->assertTrue($admin->isClient());
        // ...but the label still reads as their real role, not "Student & Client".
        $this->assertSame('Owner', $admin->accountTypeLabel());
    }

    /**
     * The owner account carries is_client, because the owner is also the person
     * who owns the client records. Signing in was reading that column directly
     * and delivering them to the client portal, so the one account that
     * administers the site never saw the back office.
     */
    public function test_an_owner_carrying_client_access_still_lands_on_the_dashboard(): void
    {
        $owner = User::factory()->create([
            'role' => 'super_admin',
            'is_admin' => true,
            'is_client' => true,
            'is_student' => false,
            'password' => 'password123',
        ]);

        $this->post(route('login'), $this->shielded([
            'email' => $owner->email,
            'password' => 'password123',
        ]))->assertRedirect(route('dashboard'));

        // And the proposal detour is for clients hiring somebody, not for the
        // person being hired, however few inquiries their own account has.
        $this->assertSame(0, ProjectInquiry::where('user_id', $owner->id)->count());
        $this->actingAs($owner)->get(route('portal.index'))->assertOk();
    }
}
