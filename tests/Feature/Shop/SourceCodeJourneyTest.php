<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Buying source code, from a stranger arriving to a zip on their disk.
 *
 * PurchaseJourneyTest already proves the billing seam. That access is granted
 * by payment, exactly once, and never before. This is the other half: that the
 * shop cannot take money for something it has no way to hand over, and that
 * somebody who has paid can actually get the file and find out how to run it.
 */
class SourceCodeJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    private function pay(Invoice $invoice): void
    {
        app(BillingService::class)->recordPayment(
            $invoice, PaymentMethod::Flutterwave, (string) $invoice->balance, ['reference' => 'TEST-REF']
        );
    }

    private function sellable(array $overrides = []): Product
    {
        return Product::factory()->create($overrides + [
            'name' => 'InvetoTrack, Inventory Management System',
            'price' => '450000.00',
            'type' => 'template',
            'version' => '2.1',
            'whats_inside' => ['Laravel back office', 'REST API', 'Flutter app'],
            'stack' => ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Flutter 3'],
            'requirements' => ['PHP 8.2 or newer', 'Composer 2 and MySQL 8'],
            'install_guide' => "## 1. Unzip it\n\nOpen a terminal in the folder.\n\n"
                ."```bash\ncomposer install\n```\n\n## If something goes wrong\n\nRead the log.",
            'license_terms' => 'One developer, unlimited client projects.',
        ]);
    }

    // nothing is sold that cannot be handed over

    public function test_an_undeliverable_product_cannot_be_put_in_a_basket(): void
    {
        $product = Product::factory()->undeliverable()->create();

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id])
            ->assertRedirect();

        $this->get(route('cart.show'))->assertOk()->assertSee('Your basket is empty');
    }

    public function test_its_page_says_so_and_offers_no_way_to_pay(): void
    {
        $product = Product::factory()->undeliverable()->create(['name' => 'Not Packaged Yet']);

        $this->get(route('shop.show', $product))->assertOk()
            ->assertSee('Still being packaged')
            ->assertSee('Tell me when it is ready')
            ->assertDontSee('Add to basket')
            ->assertDontSee('Buy now');
    }

    public function test_the_listing_marks_it_rather_than_hiding_it(): void
    {
        // Described and priced is worth reading; buyable is a different claim.
        Product::factory()->undeliverable()->create(['name' => 'Coming Soon Kit']);

        $this->get(route('shop.index'))->assertOk()
            ->assertSee('Coming Soon Kit')
            ->assertSee('Being packaged');
    }

    public function test_a_file_that_vanishes_after_the_basket_stops_the_invoice(): void
    {
        // A basket can sit in a session for days. The answer is not trusted
        // from whenever the item went in.
        $product = $this->sellable();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        Storage::disk('local')->delete($product->file_path);

        $this->actingAs($buyer)->post(route('checkout.place'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::count(), 'no invoice may be raised for something undeliverable');
    }

    public function test_a_product_with_only_an_external_link_is_deliverable(): void
    {
        $product = Product::factory()->hosted()->create();

        $this->assertTrue($product->isDeliverable());

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->get(route('cart.show'))->assertOk()->assertSee($product->name);
    }

    // the journey

    public function test_every_product_in_the_catalogue_has_a_thumbnail(): void
    {
        $this->seed(\Database\Seeders\SourceCodeSeeder::class);

        foreach (Product::all() as $product) {
            $this->assertNotNull($product->coverUrl(),
                "{$product->slug} has no thumbnail, draw one into "
                    ."public/images/products/{$product->slug}.svg");
        }
    }

    public function test_the_thumbnails_are_the_shape_the_cards_are_cut_for(): void
    {
        $this->seed(\Database\Seeders\SourceCodeSeeder::class);
        $seen = [];

        foreach (Product::pluck('slug') as $slug) {
            $file = public_path("images/products/{$slug}.svg");
            if (! is_file($file)) {
                continue;
            }

            $svg = (string) file_get_contents($file);
            $this->assertStringContainsString('viewBox="0 0 1600 1000"', $svg,
                "{$slug} is not 16:10 and will be cropped in the card");
            $this->assertStringNotContainsString('<image', $svg,
                "{$slug} embeds a raster; these are drawn, not pasted");

            // The brand colour is the first stop of the background gradient.
            preg_match('/<stop offset="0" stop-color="(#[0-9A-F]{6})"/i', $svg, $m);
            $this->assertNotEmpty($m, "{$slug} has no background gradient");
            $seen[$slug] = strtoupper($m[1]);
        }

        // Six items in one palette would be six colourways of one product.
        $this->assertSame(count($seen), count(array_unique($seen)),
            'two items share a brand colour: '.json_encode($seen));
    }

    public function test_an_uploaded_cover_beats_the_drawn_one(): void
    {
        $product = Product::factory()->create(['cover_image' => 'products/real-shot.png']);

        $this->assertStringContainsString('products/real-shot.png', (string) $product->coverUrl());
    }

    public function test_a_stranger_can_go_from_the_listing_to_the_file(): void
    {
        $product = $this->sellable();

        // 1. Arrives, reads the page, decides.
        $this->get(route('shop.index'))->assertOk()->assertSee($product->name);
        $this->get(route('shop.show', $product))->assertOk()
            ->assertSee('What is in the archive')
            ->assertSee('Laravel back office')
            ->assertSee('What you need to run it')
            ->assertSee('UGX 450,000');

        // 2. Fills a basket with no account at all.
        $this->assertGuest();
        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id, 'buy_now' => 1])
            ->assertRedirect(route('checkout.review'));

        // 3. Checkout asks who they are, and keeps the basket while they say.
        $this->get(route('checkout.review'))->assertRedirect(route('login'));

        $buyer = $this->buyer();
        $this->actingAs($buyer);
        $this->get(route('checkout.review'))->assertOk()->assertSee($product->name);

        // 4. Places the order. An invoice, not access.
        $this->post(route('checkout.place'))->assertRedirect();
        $invoice = Invoice::latest('id')->firstOrFail();

        $this->assertSame(0, $buyer->productLicenses()->count(),
            'an unpaid invoice must not grant anything');
        $this->get(route('shop.download', $product))->assertForbidden();

        // 5. Pays.
        $this->pay($invoice);

        // 6. The file, and how to run it.
        $this->assertSame(1, $buyer->fresh()->productLicenses()->count());

        $this->get(route('shop.downloads'))->assertOk()
            ->assertSee($product->name)
            ->assertSee('How to install it');

        $this->get(route('shop.download', $product))->assertOk()
            ->assertHeader('content-disposition');

        $this->get(route('shop.install', $product))->assertOk()
            ->assertSee('Installing '.$product->name, false)
            ->assertSee('composer install');
    }

    public function test_the_basket_survives_signing_up_for_an_account(): void
    {
        // Somebody who has never bought anything here is one of two people:
        // they sign in, or they register. Neither may lose the basket.
        $product = $this->sellable();

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $this->post(route('register'), $this->shielded([
            'name' => 'New Buyer',
            'email' => 'new.buyer@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
            'account_type' => 'student',
        ]));

        $this->assertAuthenticated();
        $this->get(route('cart.show'))->assertOk()->assertSee($product->name);
    }

    public function test_the_download_count_is_recorded_so_re_downloading_is_visible(): void
    {
        $product = $this->sellable();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));
        $this->pay(Invoice::latest('id')->firstOrFail());

        $this->actingAs($buyer)->get(route('shop.download', $product))->assertOk();
        $this->actingAs($buyer)->get(route('shop.download', $product))->assertOk();

        $this->assertSame(2, $buyer->productLicenses()->first()->download_count);
    }

    public function test_the_install_guide_belongs_to_whoever_bought_it(): void
    {
        $product = $this->sellable();
        $buyer = $this->buyer();
        $stranger = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));
        $this->pay(Invoice::latest('id')->firstOrFail());

        $this->actingAs($buyer)->get(route('shop.install', $product))->assertOk();

        // The guide is part of what was paid for, not a public page.
        $this->actingAs($stranger)->get(route('shop.install', $product))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('shop.install', $product))->assertRedirect(route('login'));
    }

    public function test_a_free_download_needs_no_payment_screen_but_still_grants_a_licence(): void
    {
        $product = $this->sellable(['price' => '0.00', 'name' => 'Systems Handover Checklist']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'))
            ->assertRedirect(route('shop.downloads'));

        $this->assertSame(1, $buyer->productLicenses()->count());
        $this->actingAs($buyer)->get(route('shop.download', $product))->assertOk();
    }

    public function test_the_quantity_stepper_updates_the_basket_without_a_second_button(): void
    {
        $product = $this->sellable();

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->patch(route('cart.update'), ['key' => 'product:'.$product->id, 'quantity' => 3]);

        $this->get(route('cart.show'))->assertOk()
            ->assertSee('UGX 1,350,000');   // 3 × 450,000

        $this->patch(route('cart.update'), ['key' => 'product:'.$product->id, 'quantity' => 0]);
        $this->get(route('cart.show'))->assertOk()->assertSee('Your basket is empty');
    }
}
