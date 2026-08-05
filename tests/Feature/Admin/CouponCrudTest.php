<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin CRUD for coupons. */
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
            'code' => 'launch10', 'type' => 'percent', 'value' => '10.00', 'is_active' => '1',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::first();
        $this->assertSame('LAUNCH10', $coupon->code);
        $this->assertTrue($coupon->is_active);
    }

    public function test_a_percent_coupon_over_100_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'TOOBIG', 'type' => 'percent', 'value' => '150.00',
        ])->assertStatus(422);

        $this->assertSame(0, Coupon::count());
    }

    public function test_an_admin_can_update_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create(['code' => 'OLD', 'type' => 'percent', 'value' => '10.00']);

        $this->actingAs($admin)->put(route('admin.coupons.update', $coupon), [
            'code' => 'NEW', 'type' => 'amount', 'value' => '5.00',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon->refresh();
        $this->assertSame('NEW', $coupon->code);
        $this->assertSame('amount', $coupon->type->value);
    }

    public function test_an_admin_can_delete_a_coupon(): void
    {
        $admin = $this->admin();
        $coupon = Coupon::create(['code' => 'GONE', 'type' => 'percent', 'value' => '10.00']);

        $this->actingAs($admin)->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_a_non_admin_cannot_manage_coupons(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.coupons.index'))->assertRedirect(route('login'));
    }

    public function test_a_course_scoped_coupon_can_be_created(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'SCOPED', 'type' => 'percent', 'value' => '10.00', 'course_id' => $course->id,
        ])->assertRedirect(route('admin.coupons.index'));

        $this->assertSame($course->id, Coupon::first()->course_id);
    }
}
