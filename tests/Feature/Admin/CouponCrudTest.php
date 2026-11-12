<?php

namespace Tests\Feature\Admin;

use App\Enums\CouponScope;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin CRUD for coupons, including the scope that decides what a code discounts. */
class CouponCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_an_admin_can_create_a_coupon(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'launch10', 'scope' => 'courses',
            'type' => 'percent', 'value' => '10.00', 'is_active' => '1',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::first();
        $this->assertSame('LAUNCH10', $coupon->code);
        $this->assertSame(CouponScope::Courses, $coupon->scope);
        $this->assertTrue($coupon->is_active);
    }

    /**
     * A validation error rather than a bare 422: the admin keeps their input
     * and sees the message on the field, instead of an error page that loses
     * the half-filled form.
     */
    public function test_a_percent_coupon_over_100_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'TOOBIG', 'scope' => 'courses', 'type' => 'percent', 'value' => '150.00',
        ])->assertSessionHasErrors('value');

        $this->assertSame(0, Coupon::count());
    }

    public function test_an_admin_can_update_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create([
            'code' => 'OLD', 'scope' => CouponScope::Courses, 'type' => 'percent', 'value' => '10.00',
        ]);

        $this->actingAs($admin)->put(route('admin.coupons.update', $coupon), [
            'code' => 'NEW', 'scope' => 'any', 'type' => 'amount', 'value' => '5.00',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon->refresh();
        $this->assertSame('NEW', $coupon->code);
        $this->assertSame('amount', $coupon->type->value);
        $this->assertSame(CouponScope::Any, $coupon->scope);
    }

    public function test_an_admin_can_delete_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create([
            'code' => 'GONE', 'scope' => CouponScope::Any, 'type' => 'percent', 'value' => '10.00',
        ]);

        $this->actingAs($admin)->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_a_non_admin_cannot_manage_coupons(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.coupons.index'))->assertRedirect(route('login'));
    }

    public function test_a_coupon_can_be_targeted_at_one_course(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'SCOPED', 'scope' => 'target', 'target_course_id' => $course->id,
            'type' => 'percent', 'value' => '10.00',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::first();
        $this->assertSame(CouponScope::Target, $coupon->scope);
        $this->assertSame($course->id, (int) $coupon->target_id);
        $this->assertTrue($coupon->appliesTo($course));
    }

    public function test_a_coupon_can_be_targeted_at_one_product(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'CODEONLY', 'scope' => 'target', 'target_product_id' => $product->id,
            'type' => 'amount', 'value' => '50000.00',
        ])->assertRedirect(route('admin.coupons.index'));

        $this->assertTrue(Coupon::first()->appliesTo($product));
    }

    public function test_a_targeted_coupon_with_no_item_chosen_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'NOTARGET', 'scope' => 'target', 'type' => 'percent', 'value' => '10.00',
        ])->assertSessionHasErrors('scope');

        $this->assertSame(0, Coupon::count());
    }

    public function test_an_expiry_before_the_start_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'BACKWARDS', 'scope' => 'any', 'type' => 'percent', 'value' => '10.00',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_an_admin_can_read_a_coupons_redemption_ledger(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create([
            'code' => 'LEDGER', 'scope' => CouponScope::Any, 'type' => 'percent', 'value' => '10.00',
        ]);

        $this->actingAs($admin)->get(route('admin.coupons.show', $coupon))
            ->assertOk()
            ->assertSee('LEDGER');
    }
}
