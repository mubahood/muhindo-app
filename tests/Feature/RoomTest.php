<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Hospital;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function admin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_create_a_room_attached_to_a_department(): void
    {
        $h = Hospital::factory()->create();
        $dept = Department::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($this->admin($h))->post('/admin/rooms', [
            'name' => 'Consult 1', 'department_id' => $dept->id,
            'type' => 'consultation', 'status' => 'available', 'capacity' => 2,
        ])->assertRedirect('/admin/rooms');

        $this->assertDatabaseHas('rooms', ['hospital_id' => $h->id, 'name' => 'Consult 1', 'department_id' => $dept->id]);
    }

    public function test_cannot_attach_a_room_to_another_hospitals_department(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $deptB = Department::factory()->create(['hospital_id' => $b->id]);

        $this->actingAs($this->admin($a))->post('/admin/rooms', [
            'name' => 'Consult X', 'department_id' => $deptB->id,
            'type' => 'consultation', 'status' => 'available', 'capacity' => 1,
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_invalid_type_or_status_is_rejected(): void
    {
        $h = Hospital::factory()->create();

        $this->actingAs($this->admin($h))->post('/admin/rooms', [
            'name' => 'Bad', 'type' => 'spaceship', 'status' => 'nope', 'capacity' => 1,
        ])->assertSessionHasErrors(['type', 'status']);
    }

    public function test_rooms_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $roomB = Room::factory()->create(['hospital_id' => $b->id, 'name' => 'HiddenRoom']);

        $this->actingAs($this->admin($a))->get('/admin/rooms')->assertDontSee('HiddenRoom');
        $this->actingAs($this->admin($a))->get("/admin/rooms/{$roomB->id}/edit")->assertNotFound();
    }
}
