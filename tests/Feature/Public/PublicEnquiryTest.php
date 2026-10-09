<?php

namespace Tests\Feature\Public;

use App\Models\ProjectInquiry;
use App\Models\User;
use App\Notifications\OwnerAlertNotification;
use App\Support\Spam\FormShield;
use App\Support\Spam\ImageCaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Hiring him without making an account first.
 *
 * Every Hire button on this site used to redirect a stranger to the
 * registration form, so the journey was: decide to hire him, then choose a
 * password and verify an email address before you may say what you want built.
 * That is the conversion, and these tests are mostly about it staying open.
 */
class PublicEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        // shielded() supplies the challenge answer; generating one here too
        // would replace the session value and invalidate what we posted.
        return $this->shielded(array_merge([
            'name' => 'Aisha Nakalema',
            'email' => 'aisha@example.com',
            'phone' => '+256 783 204 665',
            'organisation' => 'Kampala Clinic',
            'category' => 'management_system',
            'timeline' => '1_3_months',
            'description' => 'We need a system to manage patient records across two branches, with reports.',
        ], $overrides));
    }

    /* The door ------------------------------------------------------------- */

    public function test_a_stranger_reaches_a_form_rather_than_a_signup_wall(): void
    {
        $this->get('/hire')->assertRedirect(route('enquire'));
        $this->get(route('enquire'))->assertOk()->assertSee('Tell me what you want built');
    }

    public function test_the_form_asks_for_no_account(): void
    {
        $html = (string) $this->get(route('enquire'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="password"', $html);
        $this->assertStringContainsString('No account, no password', $html);
    }

    public function test_a_stranger_can_send_an_enquiry(): void
    {
        Notification::fake();

        $this->post(route('enquire.store'), $this->payload())
            ->assertRedirect(route('enquire'))
            ->assertSessionHas('enquiry_sent');

        $inquiry = ProjectInquiry::sole();

        $this->assertSame('Aisha Nakalema', $inquiry->name);
        $this->assertSame('aisha@example.com', $inquiry->email);
        $this->assertNull($inquiry->user_id, 'nobody was signed in, and that is the point');
        $this->assertSame('management_system', $inquiry->category);
    }

    /** A signed in client still gets the full proposal form, unchanged. */
    public function test_a_signed_in_client_still_goes_to_the_full_form(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $client = User::factory()->create(['role' => 'client', 'is_client' => true]);
        $client->syncSpatieRole();

        $this->actingAs($client)->get('/hire')->assertRedirect(route('propose'));
    }

    /* The owner hears about it --------------------------------------------- */

    public function test_the_owner_is_told_and_can_reply_to_the_sender(): void
    {
        Notification::fake();

        $this->post(route('enquire.store'), $this->payload());

        Notification::assertSentOnDemand(OwnerAlertNotification::class,
            function (OwnerAlertNotification $notification, array $channels, object $notifiable) {
                $mail = $notification->toMail($notifiable);

                $this->assertContains(config('company.owner_alerts'), (array) $notifiable->routes['mail']);
                // Reply goes to the person who wrote in, not to the site mailbox.
                $this->assertSame([['aisha@example.com', null]], $mail->replyTo);
                $this->assertStringContainsString('Aisha Nakalema', $mail->subject);

                return true;
            });
    }

    public function test_a_new_registration_tells_the_owner(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $this->post('/register', $this->shielded([
            'name' => 'Brian Mugenyi',
            'email' => 'brian@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'terms' => '1',
            'account_type' => 'student',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'brian@example.com']);

        Notification::assertSentOnDemand(OwnerAlertNotification::class);
    }

    /* The bot layer --------------------------------------------------------- */

    public function test_a_wrong_captcha_answer_is_refused(): void
    {
        ImageCaptcha::regenerate();

        $this->post(route('enquire.store'), $this->shielded([
            'name' => 'Bot', 'email' => 'bot@example.com', 'phone' => '+256700000000',
            'category' => 'website', 'timeline' => 'asap',
            'description' => 'This submission has a wrong captcha answer in it entirely.',
            ImageCaptcha::FIELD => 'WRONG',
        ]))->assertSessionHasErrors(ImageCaptcha::FIELD);

        $this->assertSame(0, ProjectInquiry::count());
    }

    public function test_a_missing_captcha_answer_is_refused(): void
    {
        $payload = $this->payload();
        unset($payload[ImageCaptcha::FIELD]);

        $this->post(route('enquire.store'), $payload)->assertSessionHasErrors(ImageCaptcha::FIELD);
        $this->assertSame(0, ProjectInquiry::count());
    }

    /**
     * The challenge is burned whether the answer was right or wrong, so a
     * script cannot keep one image and brute force five characters against it.
     */
    public function test_an_answer_cannot_be_replayed(): void
    {
        Notification::fake();
        $payload = $this->payload();

        $this->post(route('enquire.store'), $payload)->assertSessionHas('enquiry_sent');
        $this->post(route('enquire.store'), $payload)->assertSessionHasErrors(ImageCaptcha::FIELD);

        $this->assertSame(1, ProjectInquiry::count());
    }

    public function test_the_honeypot_still_covers_the_form(): void
    {
        Notification::fake();

        $this->post(route('enquire.store'), $this->payload([FormShield::HONEYPOT => 'http://spam.example']))
            ->assertRedirect()->assertSessionHas('enquiry_sent');

        // Answered as success so a bot learns nothing, and written nowhere.
        $this->assertSame(0, ProjectInquiry::count());
    }

    public function test_bad_details_are_refused(): void
    {
        $this->post(route('enquire.store'), $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->post(route('enquire.store'), $this->payload(['description' => 'Too short.']))
            ->assertSessionHasErrors('description');

        $this->assertSame(0, ProjectInquiry::count());
    }

    /* The image itself ------------------------------------------------------ */

    public function test_the_captcha_endpoint_serves_a_real_png(): void
    {
        $response = $this->get(route('captcha.image'))->assertOk();

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // The PNG magic number, so this fails if the endpoint ever starts
        // returning an error page with an image content type on it.
        $this->assertSame("\x89PNG\r\n\x1a\n", substr((string) $response->getContent(), 0, 8));
    }

    /** Characters that are read wrong by people are not in the alphabet. */
    public function test_the_alphabet_avoids_ambiguous_characters(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $answer = ImageCaptcha::regenerate();

            $this->assertSame(5, strlen($answer));
            $this->assertDoesNotMatchRegularExpression('/[01OIl52ZSB8]/', $answer,
                "{$answer} contains a character people mistype");
        }
    }
}
