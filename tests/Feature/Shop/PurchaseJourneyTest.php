<?php

namespace Tests\Feature\Shop;

use App\Enums\PaymentMethod;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductLicense;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The purchase journey end to end: browse, basket, checkout, invoice, payment,
 * fulfilment, download.
 *
 * The point of these is the seam. The shop does not have its own payment code —
 * it raises an invoice and joins the path that already settles course
 * checkouts and client invoices — so what has to be proved is that access is
 * granted by *payment*, exactly once, and never before.
 */
class PurchaseJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'student', 'is_student' => true]);
    }

    private function pay(Invoice $invoice): void
    {
        // The one chokepoint every payment path goes through, gateway or not.
        app(BillingService::class)->recordPayment(
            $invoice, PaymentMethod::Flutterwave, (string) $invoice->balance, ['reference' => 'TEST-REF']
        );
    }

    public function test_a_visitor_can_browse_and_search_the_shop(): void
    {
        Product::factory()->create(['name' => 'Laravel Starter Kit']);
        Product::factory()->create(['name' => 'Figma UI Pack']);
        Product::factory()->draft()->create(['name' => 'Unfinished Thing']);

        $this->get(route('shop.index'))->assertOk()
            ->assertSee('Laravel Starter Kit')
            ->assertDontSee('Unfinished Thing');

        $this->get(route('shop.index', ['q' => 'Figma']))->assertOk()
            ->assertSee('Figma UI Pack')->assertDontSee('Laravel Starter Kit');
    }

    public function test_an_unpublished_product_is_not_reachable(): void
    {
        $product = Product::factory()->draft()->create();

        $this->get(route('shop.show', $product))->assertNotFound();
    }

    public function test_a_guest_can_fill_a_basket_before_having_an_account(): void
    {
        $product = Product::factory()->create(['name' => 'Starter Kit']);

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id])->assertRedirect();

        $this->get(route('cart.show'))->assertOk()->assertSee('Starter Kit');
    }

    public function test_checkout_requires_signing_in_but_keeps_the_basket(): void
    {
        $product = Product::factory()->create(['name' => 'Starter Kit']);
        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $this->get(route('checkout.review'))->assertRedirect(route('login'));

        // The basket lives in the session, so signing in does not lose it.
        $this->actingAs($this->buyer())->get(route('checkout.review'))->assertOk()->assertSee('Starter Kit');
    }

    public function test_a_course_and_a_product_share_one_basket_and_one_invoice(): void
    {
        // This is the whole reason the shop reuses invoices rather than adding
        // an orders table: one payment for both.
        $product = Product::factory()->create(['price' => '50000.00']);
        $course = Course::factory()->create(['is_published' => true, 'price' => '150000.00', 'currency' => 'UGX']);
        $buyer = $this->buyer();

        $this->actingAs($buyer);
        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id]);

        $this->post(route('checkout.place'))->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame('200000.00', (string) $invoice->total);
    }

    public function test_access_is_granted_only_once_the_payment_is_recorded(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'))->assertRedirect();

        $invoice = Invoice::firstOrFail();

        // Invoice raised, nothing paid: no licence, and the file is refused.
        $this->assertDatabaseCount('product_licenses', 0);
        $this->actingAs($buyer)->get(route('shop.download', $product))->assertForbidden();

        $this->pay($invoice);

        $this->assertDatabaseHas('product_licenses', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_settling_the_same_invoice_twice_does_not_duplicate_the_licence(): void
    {
        // The gateway callback and the webhook both settle; the second must be
        // a no-op. Re-firing fulfilment is the closest analogue in a test.
        $product = Product::factory()->create();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));
        $invoice = Invoice::firstOrFail();

        $this->pay($invoice);
        event(new \App\Events\Billing\InvoicePaid($invoice->fresh()));

        $this->assertSame(1, ProductLicense::where('user_id', $buyer->id)->where('product_id', $product->id)->count());
        $this->assertSame(1, (int) $product->fresh()->purchases_count);
    }

    public function test_a_paid_course_on_the_same_invoice_also_activates(): void
    {
        $product = Product::factory()->create();
        $course = Course::factory()->create(['is_published' => true, 'price' => '150000.00', 'currency' => 'UGX']);
        $buyer = $this->buyer();

        $this->actingAs($buyer);
        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id]);
        $this->post(route('checkout.place'));

        $this->pay(Invoice::firstOrFail());

        $this->assertDatabaseHas('product_licenses', ['user_id' => $buyer->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $buyer->id, 'course_id' => $course->id, 'status' => 'active',
        ]);
    }

    public function test_a_free_product_is_delivered_without_a_payment_screen(): void
    {
        $product = Product::factory()->free()->create();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'))->assertRedirect(route('shop.downloads'));

        $this->assertDatabaseHas('product_licenses', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_something_already_owned_is_not_billed_again(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->buyer();
        ProductLicense::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'granted_at' => now()]);

        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'))->assertRedirect(route('shop.downloads'));

        $this->assertSame(0, Invoice::count(), 'nothing to bill means no invoice at all');
    }

    public function test_one_person_cannot_download_what_another_person_bought(): void
    {
        $product = Product::factory()->create();
        $owner = $this->buyer();
        $stranger = $this->buyer();
        ProductLicense::create(['user_id' => $owner->id, 'product_id' => $product->id, 'granted_at' => now()]);

        $this->actingAs($stranger)->get(route('shop.download', $product))->assertForbidden();
    }

    public function test_a_guest_cannot_reach_downloads_at_all(): void
    {
        $product = Product::factory()->create();

        $this->get(route('shop.download', $product))->assertRedirect(route('login'));
        $this->get(route('shop.downloads'))->assertRedirect(route('login'));
    }

    public function test_a_withdrawn_product_drops_out_of_an_old_basket(): void
    {
        // A basket outlives the shop's catalogue; it must never bill for
        // something that has since been taken off sale.
        $product = Product::factory()->create(['name' => 'Withdrawn Kit']);
        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $product->update(['is_published' => false]);

        $this->get(route('cart.show'))->assertOk()->assertDontSee('Withdrawn Kit');
    }

    public function test_buy_now_goes_straight_to_the_review_screen(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.add'), ['type' => 'product', 'id' => $product->id, 'buy_now' => 1])
            ->assertRedirect(route('checkout.review'));
    }

    public function test_a_course_quantity_is_always_one(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'price' => '150000.00']);

        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id, 'quantity' => 5]);
        $this->post(route('cart.add'), ['type' => 'course', 'id' => $course->id]);

        $cart = app(\App\Services\Shop\Cart::class);
        $this->assertSame(1, $cart->contents()->firstWhere('type', 'course')['quantity']);
    }

    public function test_the_payment_screen_belongs_to_its_buyer_only(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($buyer)->post(route('checkout.place'));
        $invoice = Invoice::firstOrFail();

        // Someone else's order is still forbidden; the owner is forwarded to
        // the one payment screen rather than a shop-only page.
        $this->actingAs($this->buyer())->get(route('checkout.pay', $invoice))->assertForbidden();
        $this->actingAs($buyer)->followingRedirects()->get(route('checkout.pay', $invoice))
            ->assertOk()->assertSee('Pay ');
    }
}
