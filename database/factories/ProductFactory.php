<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = rtrim($this->faker->sentence(3), '.');

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'type' => 'ebook',
            'summary' => $this->faker->sentence(10),
            'description' => '## What you get'."\n\n".$this->faker->paragraph(),
            'category' => 'Engineering',
            'price' => '50000.00',
            'currency' => 'UGX',
            'file_path' => 'products/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_bytes' => 240000,
            'is_published' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => '0.00']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
