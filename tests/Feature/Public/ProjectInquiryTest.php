<?php

namespace Tests\Feature\Public;

use App\Models\ProjectInquiry;
use App\Models\User;
use App\Notifications\ProjectInquiryReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** public-w5 — §4 of PUBLIC_SITE_PLAN.md: the "Start a project" client funnel. */
class ProjectInquiryTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return $this->shielded(array_merge([
            'name' => 'Grace Nakato',
            'email' => 'grace@example.com',
            'phone' => '+256700000000',
            'organisation' => 'Nakato Clinic',
            'project_type' => 'school_clinic_system',
            'budget_range' => '2m_5m',
            'timeline' => '1_3_months',
            'description' => 'We need a patient records system for our clinic.',
        ], $overrides));
    }

    public function test_the_page_renders(): void
    {
        $this->get(route('start-a-project'))->assertOk();
    }

    public function test_submitting_a_valid_inquiry_persists_it_and_notifies_every_admin(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $response = $this->post(route('start-a-project.store'), $this->validPayload());

        $response->assertRedirect(route('start-a-project'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('project_inquiries', [
            'name' => 'Grace Nakato',
            'email' => 'grace@example.com',
            'project_type' => 'school_clinic_system',
            'status' => 'new',
        ]);

        Notification::assertSentTo($admin, ProjectInquiryReceivedNotification::class);
    }

    public function test_a_missing_required_field_fails_validation(): void
    {
        $response = $this->post(route('start-a-project.store'), $this->validPayload(['description' => '']));

        $response->assertSessionHasErrors('description');
        $this->assertDatabaseCount('project_inquiries', 0);
    }

    public function test_an_invalid_project_type_is_rejected(): void
    {
        $response = $this->post(route('start-a-project.store'), $this->validPayload(['project_type' => 'not-a-real-type']));

        $response->assertSessionHasErrors('project_type');
    }

    public function test_the_honeypot_field_silently_drops_bot_submissions(): void
    {
        Notification::fake();

        $response = $this->post(route('start-a-project.store'), $this->validPayload(['website' => 'http://spam.example']));

        $response->assertRedirect(route('start-a-project'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('project_inquiries', 0);
        Notification::assertNothingSent();
    }

    public function test_the_form_is_throttled_against_spam(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('start-a-project.store'), $this->validPayload(['email' => "spam{$i}@example.com"]));
        }

        $this->post(route('start-a-project.store'), $this->validPayload(['email' => 'spam6@example.com']))
            ->assertStatus(429);
    }

    public function test_organisation_is_optional_for_individuals(): void
    {
        $response = $this->post(route('start-a-project.store'), $this->validPayload(['organisation' => null]));

        $response->assertRedirect(route('start-a-project'));
        $this->assertDatabaseHas('project_inquiries', ['name' => 'Grace Nakato', 'organisation' => null]);
    }

    public function test_a_guest_cannot_view_the_admin_inbox(): void
    {
        $inquiry = ProjectInquiry::factory()->create();

        $this->get(route('admin.project-inquiries.index'))->assertRedirect(route('login'));
        $this->get(route('admin.project-inquiries.show', $inquiry))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_view_the_inbox_and_a_single_inquiry(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $inquiry = ProjectInquiry::factory()->create(['name' => 'Grace Nakato']);

        $this->actingAs($admin)->get(route('admin.project-inquiries.index'))->assertOk()->assertSee('Grace Nakato');
        $this->actingAs($admin)->get(route('admin.project-inquiries.show', $inquiry))->assertOk()->assertSee('Grace Nakato');
    }

    public function test_an_admin_can_change_the_inquiry_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $inquiry = ProjectInquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin)
            ->patch(route('admin.project-inquiries.status', $inquiry), ['status' => 'contacted'])
            ->assertRedirect();

        $this->assertSame('contacted', $inquiry->fresh()->status->value);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $inquiry = ProjectInquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin)
            ->patch(route('admin.project-inquiries.status', $inquiry), ['status' => 'not-a-real-status'])
            ->assertSessionHasErrors('status');
    }

    public function test_converting_an_inquiry_pre_fills_the_new_client_form(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $inquiry = ProjectInquiry::factory()->create([
            'name' => 'Grace Nakato', 'email' => 'grace@example.com',
            'phone' => '+256700000000', 'organisation' => 'Nakato Clinic',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.clients.create', ['from_inquiry' => $inquiry->id]));

        $response->assertOk();
        $response->assertSee('value="Grace Nakato"', false);
        $response->assertSee('value="grace@example.com"', false);
        $response->assertSee('value="Nakato Clinic"', false);
    }

    public function test_converting_a_nonexistent_inquiry_id_does_not_500(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.clients.create', ['from_inquiry' => 999999]))->assertOk();
    }
}
