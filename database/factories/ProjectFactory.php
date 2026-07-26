<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'project_number' => 'PRJ-'.fake()->unique()->numerify('####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'client_id' => Client::factory(),
            'status' => 'active',
            'priority' => 'medium',
            'currency' => 'UGX',
        ];
    }
}
