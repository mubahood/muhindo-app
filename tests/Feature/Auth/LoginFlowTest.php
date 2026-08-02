<?php

namespace Tests\Feature\Auth;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => bcrypt('password'), 'is_active' => true, 'is_student' => true]);
    }

    public function test_plain_sign_in_lands_on_the_dashboard(): void
    {
        $this->post(route('login'), ['email' => $this->user()->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_an_intended_url_wins_over_every_fallback(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));

        // A basket is waiting too — the explicit intended URL still wins,
        // because it says more about where they were headed than the basket.
        app(\App\Services\Shop\Cart::class)->add(
            \App\Models\Product::factory()->create(['is_published' => true])
        );

        $this->post(route('login'), ['email' => $this->user()->email, 'password' => 'password'])
            ->assertRedirect(route('account.edit'));
    }

    public function test_a_waiting_basket_sends_them_to_checkout(): void
    {
        $p = \App\Models\Product::factory()->create(['is_published' => true]);
        app(\App\Services\Shop\Cart::class)->add($p);
        $this->post(route('login'), ['email' => $this->user()->email, 'password' => 'password'])
            ->assertRedirect(route('checkout.review'));
    }

    public function test_an_intended_course_enrols_on_sign_in(): void
    {
        $c = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $u = $this->user();

        $this->post(route('login'), [
            'email' => $u->email, 'password' => 'password', 'intended_course' => $c->slug,
        ]);

        $this->assertDatabaseHas('enrollments', ['user_id' => $u->id, 'course_id' => $c->id]);
    }

    public function test_a_deactivated_account_is_refused(): void
    {
        $u = User::factory()->create(['password' => bcrypt('password'), 'is_active' => false]);
        $this->post(route('login'), ['email' => $u->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * The preview modal's call to action must take a guest through sign-up
     * carrying the course, exactly like the buy box on the same page. Posting
     * straight to enrol bounced them to login and dropped the course.
     */
    public function test_the_preview_modal_sends_a_guest_through_onboarding(): void
    {
        $c = Course::factory()->create(['is_published' => true]);
        $m = \App\Models\CourseModule::create(['course_id' => $c->id, 'title' => 'Start here', 'sort_order' => 1]);
        \App\Models\Lesson::create([
            'course_module_id' => $m->id, 'title' => 'Free lesson', 'content' => 'Hello',
            'sort_order' => 1, 'is_published' => true, 'is_free_preview' => true,
        ]);

        $html = (string) $this->get(route('courses.show', $c))->assertOk()->getContent();

        $modal = substr($html, (int) strpos($html, 'id="preview-modal"'));
        $this->assertStringContainsString(route('register', ['intended_course' => $c->slug]), $modal);
        $this->assertStringNotContainsString(route('courses.enroll', $c), $modal);
    }
}
