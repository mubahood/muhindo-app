<?php

namespace Tests\Feature\Portfolio;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_submitting_the_contact_form_persists_a_message(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
            'subject' => 'Project inquiry',
            'message' => 'I would like to discuss a project.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
            'subject' => 'Project inquiry',
        ]);
    }

    public function test_the_honeypot_field_silently_drops_bot_submissions(): void
    {
        Mail::fake();

        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'spam',
            'website' => 'http://spam.example.com',
        ]);

        // The bot must see an ordinary success redirect, not a validation error — a
        // validation error would tip it off that it was caught by the honeypot.
        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('contact_messages', ['email' => 'bot@example.com']);
    }

    public function test_a_missing_required_field_fails_validation(): void
    {
        $response = $this->post('/contact', ['name' => 'Jane']);

        $response->assertSessionHasErrors(['email', 'message']);
    }

    public function test_admin_can_view_the_message_inbox(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        ContactMessage::factory()->create(['name' => 'Jane Client']);

        $this->actingAs($admin)->get('/admin/messages')->assertOk()->assertSee('Jane Client');
    }

    public function test_the_contact_form_is_throttled_against_spam(): void
    {
        Mail::fake();

        $payload = ['name' => 'Spammer', 'email' => 'spam@example.com', 'message' => 'buy now'];

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->post('/contact', $payload);
        }

        $last->assertStatus(429);
    }
}
