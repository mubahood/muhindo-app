<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectInquiry> */
class ProjectInquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'organisation' => fake()->company(),
            'project_type' => fake()->randomElement(['website', 'web_system', 'mobile_app', 'ecommerce', 'school_clinic_system', 'other']),
            'budget_range' => fake()->randomElement(['under_2m', '2m_5m', '5m_10m', 'over_10m', 'not_sure']),
            'timeline' => fake()->randomElement(['asap', '1_3_months', '3_6_months', 'not_sure']),
            'description' => fake()->paragraph(),
            'status' => 'new',
        ];
    }
}
