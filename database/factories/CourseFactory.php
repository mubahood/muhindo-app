<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course> */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->paragraph(),
            'price' => 0,
            'currency' => 'UGX',
            'level' => 'beginner',
            'is_published' => false,
            /*
             * The column defaults to TRUE in the database, which is the right
             * default for real data: a course imported or created by hand is
             * not on sale before anybody has looked at it.
             *
             * A factory is the opposite situation. It builds an ordinary
             * course for a test that means "a student buys a course", and
             * inheriting the cautious default made 65 existing tests fail
             * against a shop that had silently closed. A test that wants a
             * closed course says so.
             */
            'is_coming_soon' => false,
        ];
    }
}
