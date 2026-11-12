<?php

namespace Tests\Feature\Shop;

use App\Enums\CouponScope;
use App\Enums\RedemptionStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coupons in the shop basket, which previously had none.
 *
 * The case worth the most care is a mixed basket: a code scoped to one product
 * must discount that product and nothing else, or a 20%-off-source-code
 * campaign quietly takes 20% off a course sitting beside it.
 */
class BasketCouponTest extends TestCase
{
    use RefreshDatabase;

    private function deliverableProduct(array $attributes = []): Product
    {
        Storage::fake('local');

        return Product::factory()->create($attributes + [
            'is_published' => true,
            'file_path' => UploadedFile::fake()->create('src.zip', 10)->store('products'),
            'file_name' => 'src.zip',
            'currency' => 'UGX',
        ]);
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create($attributes + [
            'code' => 'CODE20',
            'scope' => CouponScope::Products,
            'type' => 'percent',
            'value' => '20.00',
        ]);
    }

    public function test_a_product_coupon_discounts_the_basket(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '550000.00']);
        $this->coupon();

        $this->actingAs($user)
            ->post(route('cart.add'), ['type' => 'product', 'id' => $product->id])
            ->assertRedirect();

        $this->actingAs($user)->post(route('checkout.place'), ['coupon' => 'CODE20']);

        $invoice = $user->invoices()->sole();
        $this->assertEquals('550000.00', $invoice->subtotal);
        $this->assertEquals('110000.00', $invoice->discount);
        $this->assertEquals('440000.00', $invoice->total);

        $redemption = CouponRedemption::sole();
        $this->assertSame($invoice->id, $redemption->invoice_id);
        $this->assertSame(RedemptionStatus::Reserved, $redemption->status);
    }

    public function test_a_product_coupon_discounts_only_the_product_in_a_mixed_basket(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '500000.00']);
        $course = Course::factory()->create([
            'price' => '130000.00', 'currency' => 'UGX',
            'is_published' => true, 'is_coming_soon' => false,
        ]);
        $this->coupon();

        $this->actingAs($user)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($user)->post(route('cart.add'), ['type' => 'course', 'id' => $course->id]);

        $this->actingAs($user)->post(route('checkout.place'), ['coupon' => 'CODE20']);

        $invoice = $user->invoices()->sole();

        // 20% of the product only, not of the 630,000 basket.
        $this->assertEquals('630000.00', $invoice->subtotal);
        $this->assertEquals('100000.00', $invoice->discount);
        $this->assertEquals('530000.00', $invoice->total);
    }

    public function test_a_code_that_matches_nothing_in_the_basket_is_refused_and_the_basket_survives(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '550000.00']);

        // Scoped to courses, but the basket holds a product.
        $this->coupon(['scope' => CouponScope::Courses]);

        $this->actingAs($user)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $this->actingAs($user)->post(route('checkout.place'), ['coupon' => 'CODE20'])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');

        // No invoice raised, nothing consumed, and the basket is intact so the
        // buyer can simply retype the code.
        $this->assertSame(0, $user->invoices()->count());
        $this->assertSame(0, CouponRedemption::count());
        $this->assertSame(1, app(\App\Services\Shop\Cart::class)->contents()->count());
    }

    public function test_an_unknown_code_is_refused_without_raising_an_invoice(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '220000.00']);

        $this->actingAs($user)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);

        $this->actingAs($user)->post(route('checkout.place'), ['coupon' => 'NOSUCHCODE'])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');

        $this->assertSame(0, $user->invoices()->count());
    }

    public function test_checkout_without_a_coupon_still_works(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '220000.00']);

        $this->actingAs($user)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($user)->post(route('checkout.place'));

        $invoice = $user->invoices()->sole();
        $this->assertEquals('0.00', $invoice->discount);
        $this->assertEquals('220000.00', $invoice->total);
        $this->assertSame(0, CouponRedemption::count());
    }

    public function test_a_blank_coupon_field_is_not_treated_as_a_code(): void
    {
        $user = User::factory()->create();
        $product = $this->deliverableProduct(['price' => '220000.00']);

        $this->actingAs($user)->post(route('cart.add'), ['type' => 'product', 'id' => $product->id]);
        $this->actingAs($user)->post(route('checkout.place'), ['coupon' => '   ']);

        $this->assertEquals('220000.00', $user->invoices()->sole()->total);
    }
}
