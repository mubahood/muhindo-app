<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Testimonials are managed from the back office, not by editing JSON by hand. */
class TestimonialAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_an_admin_can_add_a_testimonial_and_it_reaches_the_home_page(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.testimonials.store'), [
            'quote' => 'He delivered the system and trained our team to run it.',
            'name' => 'A Real Person',
            'role' => 'Permanent Secretary',
            'org' => 'A Ministry',
            'photo' => UploadedFile::fake()->image('face.jpg', 400, 400),
        ])->assertRedirect();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('He delivered the system and trained our team to run it.')
            ->assertSee('A Real Person');
    }

    public function test_removing_a_testimonial_takes_it_off_the_home_page(): void
    {
        Settings::set('portfolio.testimonials', json_encode([
            ['quote' => 'First quote', 'name' => 'One'],
            ['quote' => 'Second quote', 'name' => 'Two'],
        ]));

        $this->actingAs($this->admin())->delete(route('admin.testimonials.destroy', 0))->assertRedirect();

        $response = $this->get(route('home'))->assertOk();
        $response->assertDontSee('First quote');
        // The surviving entry must be reindexed, not left with a hole.
        $response->assertSee('Second quote');
    }

    public function test_a_quote_and_a_name_are_both_required(): void
    {
        // A quote with nobody attached is not a testimonial, it is a slogan.
        $this->actingAs($this->admin())
            ->post(route('admin.testimonials.store'), ['quote' => 'Anonymous praise'])
            ->assertSessionHasErrors('name');
    }

    public function test_the_public_cannot_reach_the_editor(): void
    {
        $this->get(route('admin.testimonials.index'))->assertRedirect();
    }
}
