<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Users\Index;
use App\Mail\WelcomeCredentials;
use App\Models\Hospital;
use App\Models\User;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UserModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function actingAdmin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    public function test_modal_creates_user_and_emails_credentials(): void
    {
        Mail::fake();
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);

        Livewire::test(Index::class)
            ->call('create')
            ->set('name', 'New Nurse')
            ->set('email', 'nurse@example.com')
            ->set('role', 'nurse')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $user = User::where('email', 'nurse@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($h->id, $user->hospital_id);
        $this->assertTrue((bool) $user->password_change_required);
        Mail::assertSent(WelcomeCredentials::class);
    }

    public function test_modal_validates_unique_email(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        User::factory()->create(['hospital_id' => $h->id, 'email' => 'taken@example.com']);

        Livewire::test(Index::class)
            ->call('create')
            ->set('name', 'X')
            ->set('email', 'taken@example.com')
            ->set('role', 'nurse')
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_modal_edits_user_and_optional_password_reset(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        $target = User::factory()->create(['hospital_id' => $h->id, 'name' => 'Old', 'role' => 'nurse']);

        Livewire::test(Index::class)
            ->call('edit', $target->id)
            ->assertSet('name', 'Old')
            ->set('name', 'Renamed')
            ->set('password', 'NewPass123')
            ->set('password_confirmation', 'NewPass123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Renamed', $target->fresh()->name);
        $this->assertTrue((bool) $target->fresh()->password_change_required);
    }

    public function test_cannot_delete_own_account(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->actingAdmin($h);

        Livewire::test(Index::class)->call('delete', $admin->id);

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_cannot_edit_another_hospitals_user(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $this->actingAdmin($a);
        $userB = User::factory()->create(['hospital_id' => $b->id]);

        Livewire::test(Index::class)->call('edit', $userB->id)->assertStatus(404);
    }

    public function test_forbidden_without_manage_users(): void
    {
        $h = Hospital::factory()->create();
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'nurse']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        Livewire::test(Index::class)->assertForbidden();
    }
}
