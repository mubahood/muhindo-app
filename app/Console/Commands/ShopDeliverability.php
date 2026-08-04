<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * What the shop can and cannot hand over.
 *
 * The worst thing this shop could do is take somebody's money for a file that
 * is not there. Three separate places refuse an undeliverable product — the
 * buy button, the basket and the invoice — and this is the fourth: the view
 * from outside, so the owner knows what is still waiting on an upload.
 *
 * A described-but-unreleased product stays listed; only its buy button is
 * gone. `--hold` removes it from the shop altogether, which is a different
 * decision and should be a deliberate one.
 */
class ShopDeliverability extends Command
{
    protected $signature = 'shop:deliverability
        {--hold : Take anything unfulfillable off the shop entirely}';

    protected $description = 'Report which products can actually be handed over';

    public function handle(): int
    {
        $products = Product::orderBy('sort_order')->orderBy('id')->get();

        if ($products->isEmpty()) {
            $this->info('No products.');

            return self::SUCCESS;
        }

        $rows = [];
        $held = 0;
        $sellable = 0;

        foreach ($products as $product) {
            $ok = $product->isDeliverable();

            if ($ok && $product->is_published) {
                $sellable++;
            }

            if (! $ok && $product->is_published && $this->option('hold')) {
                $product->forceFill(['is_published' => false])->save();
                $held++;
            }

            $rows[] = [
                $product->slug,
                $product->is_published ? 'published' : 'held',
                $product->isFree() ? 'free' : $product->currency.' '.number_format((float) $product->price),
                $ok ? '✓ deliverable' : '✗ '.$product->undeliverableReason(),
            ];
        }

        $this->table(['Product', 'State', 'Price', 'Can it be handed over?'], $rows);

        if ($held > 0) {
            $this->warn("Took {$held} product(s) off sale because there is nothing to deliver.");
        }

        $waiting = $products->reject->isDeliverable();

        if ($waiting->isNotEmpty()) {
            $this->newLine();
            $this->line('<options=bold>Waiting on an upload:</>');
            foreach ($waiting as $product) {
                $this->line("  • {$product->name}");
            }
            $this->newLine();
            $this->line('  Upload a .zip in the admin (Products → edit → File), then publish.');
            $this->line('  Nothing can be added to a basket until then.');
        }

        $this->newLine();
        $this->info("{$sellable} product(s) are on sale and deliverable.");

        return self::SUCCESS;
    }
}
