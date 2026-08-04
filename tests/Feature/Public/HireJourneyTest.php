<?php

namespace Tests\Feature\Public;

use App\Models\ProjectInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hiring, from the button to the portal.
 *
 * There used to be three ways in — a contact form, a lead form and a hire
 * button — and none of them ended anywhere a person could return to. There is
 * one now: press Hire Me, make an account, say what you want built, and watch
 * it move. This walks it, and pins the two rules that make it work.
 */
class HireJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create([
            'role' => 'client', 'is_client' => true, 'is_student' => false,
        ]);
    }

    private function proposal(): array
    {
        return [
            'title' => 'Stock system for three branches',
            'category' => 'management_system',
            'description' => 'We count stock on paper at three branches and the numbers never '
                .'agree by the end of the week. I want one place that shows all three.',
            'who_uses_it' => 'Twelve shop attendants and me.',
            'success_looks_like' => 'I can see stock everywhere without phoning anybody.',
            'timeline' => '1_3_months',
            'budget_currency' => 'UGX',
            'budget_amount' => '12000000',
            'organisation' => 'Kampala Hardware',
            'phone' => '+256 700 111 222',
        ];
    }

    // ── one door ─────────────────────────────────────────────────────────

    public function test_hire_sends_a_stranger_to_make_an_account(): void
    {
        $this->get(route('hire'))
            ->assertRedirect(route('register', ['account_type' => 'client']));
    }

    public function test_the_old_contact_and_lead_urls_land_on_it_rather_than_404(): void
    {
        // Both were linked from the site for months and are in people's history.
        $this->get('/contact')->assertRedirect(route('hire'));
        $this->get('/start-a-project')->assertRedirect(route('hire'));
    }

    public function test_the_register_page_says_where_it_is_in_the_journey(): void
    {
        // A hire button that asks for a password without explaining itself is
        // where most of these are abandoned.
        $this->get(route('register', ['account_type' => 'client']))->assertOk()
            ->assertSee('Step 1 of 2')
            ->assertSee('tell me what you want built', false);
    }

    // ── the rule: a client with nothing proposed is asked ────────────────

    public function test_registering_as_a_client_lands_on_the_proposal(): void
    {
        $this->post(route('register'), $this->shielded([
            'name' => 'Grace Nakato',
            'email' => 'grace@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
            'account_type' => 'client',
        ]))->assertRedirect(route('propose'));

        $this->assertAuthenticated();
    }

    public function test_signing_in_lands_there_too_until_something_is_proposed(): void
    {
        $client = $this->client();

        $this->post(route('login'), [
            'email' => $client->email, 'password' => 'password',
        ])->assertRedirect(route('propose'));
    }

    public function test_the_portal_sends_them_back_until_they_have(): void
    {
        // Reaching it by URL, bookmark or the header must not show an empty
        // portal with no obvious next step.
        $this->actingAs($this->client())->get(route('portal.index'))
            ->assertRedirect(route('propose'));
    }

    public function test_a_student_is_never_asked_for_a_proposal(): void
    {
        $student = User::factory()->create([
            'role' => 'student', 'is_student' => true, 'is_client' => false,
        ]);

        $this->post(route('login'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    // ── the proposal itself ──────────────────────────────────────────────

    public function test_the_form_asks_the_questions_a_price_needs(): void
    {
        $this->actingAs($this->client())->get(route('propose'))->assertOk()
            ->assertSee('What are you building?')
            ->assertSee('Who will actually use it?')
            ->assertSee('When, and what can you spend?')
            ->assertSee('UGX')
            ->assertSee('USD')
            ->assertSee('What happens next');
    }

    public function test_sending_one_files_it_and_shows_it_in_the_portal(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('propose.store'), $this->proposal())
            ->assertRedirect(route('portal.index'));

        $inquiry = ProjectInquiry::firstOrFail();
        $this->assertSame($client->id, $inquiry->user_id);
        $this->assertSame('Stock system for three branches', $inquiry->title);
        $this->assertSame('UGX', $inquiry->budget_currency);
        $this->assertSame('new', $inquiry->status->value);
        $this->assertNotNull($inquiry->submitted_at);

        // The client's own name and email come from the account, never retyped.
        $this->assertSame($client->email, $inquiry->email);

        $this->actingAs($client)->get(route('portal.index'))->assertOk()
            ->assertSee('Stock system for three branches')
            ->assertSee('UGX 12,000,000')
            ->assertSee('Muhindo reads it');
    }

    public function test_a_budget_is_optional_because_a_guessed_number_is_worse(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('propose.store'), ['budget_amount' => ''] + $this->proposal())
            ->assertRedirect(route('portal.index'));

        $this->assertSame('Not said', ProjectInquiry::firstOrFail()->budgetLabel());
    }

    public function test_a_description_too_short_to_price_is_refused(): void
    {
        $this->actingAs($this->client())
            ->post(route('propose.store'), ['description' => 'a system'] + $this->proposal())
            ->assertSessionHasErrors('description');

        $this->assertSame(0, ProjectInquiry::count());
    }

    public function test_asking_twice_is_not_a_thing(): void
    {
        $client = $this->client();
        $this->actingAs($client)->post(route('propose.store'), $this->proposal());

        // Having proposed, hire and the form both lead to the portal.
        $this->actingAs($client)->get(route('propose'))->assertRedirect(route('portal.index'));
        $this->actingAs($client)->get(route('hire'))->assertRedirect(route('portal.index'));
        $this->actingAs($client)->get(route('portal.index'))->assertOk();
    }

    public function test_proposing_makes_somebody_a_client_without_asking_twice(): void
    {
        // A student who presses Hire Me has already said what they want by
        // pressing it. Making them pick "client" again is a question with one
        // sensible answer.
        $student = User::factory()->create([
            'role' => 'student', 'is_student' => true, 'is_client' => false,
        ]);

        $this->actingAs($student)->get(route('propose'))->assertOk();

        $this->assertTrue($student->fresh()->is_client);
        $this->assertNotNull($student->fresh()->client);
    }

    public function test_a_guest_cannot_reach_the_proposal_form(): void
    {
        $this->get(route('propose'))->assertRedirect(route('login'));
        $this->post(route('propose.store'), $this->proposal())->assertRedirect(route('login'));
    }
}
