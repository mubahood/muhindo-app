<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(6), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'body' => collect($this->faker->paragraphs(6))->implode("\n\n"),
            'category' => $this->faker->randomElement(['Engineering', 'Teaching', 'Systems']),
            'tags' => ['laravel', 'architecture'],
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 200)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }
}
