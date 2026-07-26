<?php

namespace Tests\Feature\Portal;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProjectIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function clientWithProject(): array
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create(['client_id' => $client->id]);

        return [$user, $client, $project];
    }

    public function test_a_client_can_view_their_own_project(): void
    {
        [$user, , $project] = $this->clientWithProject();

        $this->actingAs($user)->get(route('portal.project', $project))->assertOk();
    }

    public function test_a_client_cannot_view_another_clients_project(): void
    {
        [, , $project] = $this->clientWithProject();
        [$otherUser] = $this->clientWithProject();

        $this->actingAs($otherUser)->get(route('portal.project', $project))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [, , $project] = $this->clientWithProject();

        $this->get(route('portal.project', $project))->assertRedirect('/login');
    }

    public function test_a_student_role_cannot_view_a_project_at_all(): void
    {
        [, , $project] = $this->clientWithProject();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('portal.project', $project))->assertForbidden();
    }

    public function test_confidential_documents_are_hidden_from_the_client_view(): void
    {
        [$user, , $project] = $this->clientWithProject();
        $project->documents()->create([
            'title' => 'Internal notes', 'file_path' => 'x.txt', 'file_name' => 'x.txt', 'is_confidential' => true,
        ]);
        $project->documents()->create([
            'title' => 'Contract', 'file_path' => 'y.txt', 'file_name' => 'y.txt', 'is_confidential' => false,
        ]);

        $response = $this->actingAs($user)->get(route('portal.project', $project));

        $response->assertOk()->assertSee('Contract')->assertDontSee('Internal notes');
    }
}
