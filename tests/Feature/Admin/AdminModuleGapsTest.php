<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\ProjectInquiry;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The three modules an audit of every admin route found to be missing a
 * control that its content genuinely needs.
 */
class AdminModuleGapsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    // Testimonials could be added and deleted, never edited

    public function test_a_testimonial_can_be_edited_without_losing_its_photo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'quote' => 'He built our system.',
            'name' => 'Prof Jude Lubega',
            'role' => 'Vice Chancelor',   // the typo being fixed
            'org' => 'Nkumba University',
            'photo' => UploadedFile::fake()->image('jude.jpg', 400, 400),
        ]);

        $before = json_decode((string) Settings::get('portfolio.testimonials'), true);
        $photo = $before[0]['photo'];
        $this->assertNotNull($photo);

        $this->actingAs($admin)->post(route('admin.testimonials.update', 0), [
            'quote' => 'He built our system.',
            'name' => 'Prof Jude Lubega',
            'role' => 'Vice Chancellor',
            'org' => 'Nkumba University',
        ])->assertSessionHas('success');

        $after = json_decode((string) Settings::get('portfolio.testimonials'), true);
        $this->assertSame('Vice Chancellor', $after[0]['role']);
        // Correcting a spelling mistake must not throw away their photograph.
        $this->assertSame($photo, $after[0]['photo']);
        $this->assertCount(1, $after);
    }

    public function test_a_testimonial_photo_can_be_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'name' => 'Dr Chongomweru Halimu',
            'photo' => UploadedFile::fake()->image('one.jpg', 300, 300),
        ]);
        $first = json_decode((string) Settings::get('portfolio.testimonials'), true)[0]['photo'];

        $this->actingAs($admin)->post(route('admin.testimonials.update', 0), [
            'name' => 'Dr Chongomweru Halimu',
            'photo' => UploadedFile::fake()->image('two.jpg', 300, 300),
        ]);
        $second = json_decode((string) Settings::get('portfolio.testimonials'), true)[0]['photo'];

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing(substr($first, strlen('storage/')));

        $this->actingAs($admin)->post(route('admin.testimonials.update', 0), [
            'name' => 'Dr Chongomweru Halimu',
            'remove_photo' => '1',
        ]);
        $this->assertNull(json_decode((string) Settings::get('portfolio.testimonials'), true)[0]['photo']);
    }

    public function test_editing_one_testimonial_leaves_the_others_alone(): void
    {
        $admin = $this->admin();

        foreach (['First Person', 'Second Person', 'Third Person'] as $name) {
            $this->actingAs($admin)->post(route('admin.testimonials.store'), ['name' => $name]);
        }

        $this->actingAs($admin)->post(route('admin.testimonials.update', 1), ['name' => 'Renamed Person']);

        $all = json_decode((string) Settings::get('portfolio.testimonials'), true);
        $this->assertSame(['First Person', 'Renamed Person', 'Third Person'], array_column($all, 'name'));
        // A hole in the keys would serialise as a JSON object and break the
        // home page's iteration over the set.
        $this->assertSame([0, 1, 2], array_keys($all));
    }

    public function test_editing_a_testimonial_that_is_gone_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.testimonials.update', 7), ['name' => 'Nobody'])
            ->assertSessionHas('error');
    }

    // The inbox could only ever grow

    public function test_a_contact_message_can_be_deleted(): void
    {
        $message = ContactMessage::create([
            'name' => 'Spammer', 'email' => 'spam@example.com', 'message' => 'Buy things',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.messages.destroy', $message))
            ->assertSessionHas('success');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_a_project_inquiry_can_be_deleted(): void
    {
        $inquiry = ProjectInquiry::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Spammer', 'email' => 'spam@example.com',
            'project_type' => 'website', 'description' => 'Buy things',
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.project-inquiries.destroy', $inquiry))
            ->assertRedirect(route('admin.project-inquiries.index'));

        $this->assertSame(0, ProjectInquiry::count());
    }

    // None of it is reachable without staff credentials

    public function test_none_of_this_is_open_to_a_student(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $message = ContactMessage::create([
            'name' => 'Real', 'email' => 'real@example.com', 'message' => 'Hello',
        ]);
        $inquiry = ProjectInquiry::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Real', 'email' => 'real@example.com',
            'project_type' => 'website', 'description' => 'A site',
        ]);

        $this->actingAs($student)->delete(route('admin.messages.destroy', $message))->assertRedirect();
        $this->actingAs($student)->delete(route('admin.project-inquiries.destroy', $inquiry))->assertRedirect();
        $this->actingAs($student)->post(route('admin.testimonials.update', 0), ['name' => 'Hacked'])->assertRedirect();

        $this->assertSame(1, ContactMessage::count());
        $this->assertSame(1, ProjectInquiry::count());
    }
}
