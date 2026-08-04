<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
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
            'file_path' => 'products/'.Str::random(12).'.zip',
            'file_name' => 'source-code.zip',
            'file_bytes' => 240000,
            'is_published' => true,
        ];
    }

    /**
     * Nothing is sold that cannot be handed over, so the default product has
     * its file actually on disk — otherwise every factory-made product would
     * be blocked at the basket and the tests would be testing the guard
     * rather than the journey.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->file_path && ! Storage::disk('local')->exists($product->file_path)) {
                Storage::disk('local')->put($product->file_path, 'zip-bytes');
            }
        });
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => '0.00']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    /** Hosted elsewhere: deliverable without anything on our own disk. */
    public function hosted(): static
    {
        return $this->state(fn () => [
            'file_path' => null, 'file_name' => null, 'file_bytes' => null,
            'external_url' => 'https://github.com/mubahood/example',
        ]);
    }

    /** Published, priced, and with nothing behind it. The state the guard exists for. */
    public function undeliverable(): static
    {
        return $this->state(fn () => [
            'file_path' => null, 'file_name' => null, 'file_bytes' => null,
            'external_url' => null,
        ]);
    }
}
