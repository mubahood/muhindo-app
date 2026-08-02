<?php

namespace App\Services\Shop;

use App\Models\Course;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;

/**
 * The basket.
 *
 * Holds products *and* courses, because they end up on one invoice and a
 * visitor buying a template and a course should pay once, not twice. Lines are
 * keyed "product:12" / "course:3", which is also what makes adding the same
 * thing twice a no-op instead of a duplicate line.
 *
 * Lives in the session rather than the database so a guest can fill a basket
 * before they have an account — signing in at checkout keeps what they chose.
 */
class Cart
{
    private const KEY = 'cart.lines';

    public function __construct(private readonly Session $session) {}

    /** @return array<string,int> line key => quantity */
    public function lines(): array
    {
        /** @var array<string,int> $lines */
        $lines = $this->session->get(self::KEY, []);

        return $lines;
    }

    public function add(Product|Course $item, int $quantity = 1): void
    {
        $lines = $this->lines();
        $key = $this->keyFor($item);

        // A course is a right of access, not a stack of goods — buying two of
        // the same course is meaningless, so its quantity is pinned at one.
        $lines[$key] = $item instanceof Course
            ? 1
            : min(99, max(1, ($lines[$key] ?? 0) + $quantity));

        $this->session->put(self::KEY, $lines);
    }

    public function setQuantity(string $key, int $quantity): void
    {
        $lines = $this->lines();

        if (! isset($lines[$key])) {
            return;
        }

        if ($quantity < 1) {
            unset($lines[$key]);
        } else {
            $lines[$key] = str_starts_with($key, 'course:') ? 1 : min(99, $quantity);
        }

        $this->session->put(self::KEY, $lines);
    }

    public function remove(string $key): void
    {
        $lines = $this->lines();
        unset($lines[$key]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function isEmpty(): bool
    {
        return $this->contents()->isEmpty();
    }

    public function count(): int
    {
        return $this->contents()->sum('quantity');
    }

    /**
     * Resolve the session's keys into real models, dropping anything that has
     * since been unpublished or deleted. A basket is long-lived, so it must
     * never be able to bill for something no longer on sale.
     *
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    public function contents(): \Illuminate\Support\Collection
    {
        $lines = $this->lines();
        if ($lines === []) {
            return collect();
        }

        $productIds = $this->idsFor($lines, 'product');
        $courseIds = $this->idsFor($lines, 'course');

        $products = $productIds ? Product::published()->findMany($productIds)->keyBy('id') : collect();
        $courses = $courseIds ? Course::where('is_published', true)->findMany($courseIds)->keyBy('id') : collect();

        $out = collect();

        foreach ($lines as $key => $quantity) {
            [$type, $id] = explode(':', $key, 2);
            $model = $type === 'product' ? $products->get((int) $id) : $courses->get((int) $id);

            if ($model === null) {
                continue;                         // withdrawn since it was added
            }

            $unitPrice = (string) $model->price;

            $out->push([
                'key' => $key,
                'type' => $type,
                'model' => $model,
                'name' => $type === 'product' ? $model->name : $model->title,
                'quantity' => (int) $quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($unitPrice, (string) $quantity, 2),
                'currency' => $model->currency ?? 'UGX',
            ]);
        }

        return $out;
    }

    public function subtotal(): string
    {
        return $this->contents()->reduce(fn (string $carry, array $line) => bcadd($carry, $line['line_total'], 2), '0.00');
    }

    /** The currency every line is in, or null when they disagree. */
    public function currency(): ?string
    {
        $currencies = $this->contents()->pluck('currency')->unique();

        return $currencies->count() === 1 ? $currencies->first() : ($currencies->isEmpty() ? 'UGX' : null);
    }

    public function keyFor(Product|Course $item): string
    {
        return ($item instanceof Product ? 'product' : 'course').':'.$item->getKey();
    }

    /**
     * @param  array<string,int>  $lines
     * @return list<int>
     */
    private function idsFor(array $lines, string $type): array
    {
        $ids = [];
        foreach (array_keys($lines) as $key) {
            if (str_starts_with($key, $type.':')) {
                $ids[] = (int) substr($key, strlen($type) + 1);
            }
        }

        return $ids;
    }
}
