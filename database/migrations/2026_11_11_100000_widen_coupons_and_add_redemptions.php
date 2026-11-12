<?php

use App\Enums\CouponScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons grow up: they can discount products as well as courses, they can be
 * scheduled, they can be limited per customer, and every use leaves a record.
 *
 * The single nullable `course_id` is replaced by a scope plus an optional
 * polymorphic target, because "10% off everything" and "50% off this one
 * product" are both things a shop needs to say and neither fitted.
 *
 * `coupon_redemptions` is the point of the whole migration. `used_count` was a
 * bare integer, so the one question a coupon exists to answer — did this
 * campaign make money — could not be asked. A row per use, carrying the
 * discount it granted and the invoice it granted it against, answers it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('scope', 16)->default(CouponScope::Any->value)->after('code');
            $table->nullableMorphs('target');              // set only when scope = target
            $table->timestamp('starts_at')->nullable()->after('expires_at');
            $table->unsignedInteger('max_uses_per_user')->nullable()->after('max_uses');
            $table->decimal('min_subtotal', 12, 2)->nullable()->after('value');
            $table->string('description')->nullable()->after('is_active');
        });

        // Carry the existing scoping across before the column goes. A coupon
        // tied to a course becomes a targeted coupon; one with no course was
        // already "any course", which is now expressed as a scope.
        foreach (DB::table('coupons')->select('id', 'course_id')->get() as $row) {
            DB::table('coupons')->where('id', $row->id)->update(
                $row->course_id === null
                    ? ['scope' => CouponScope::Courses->value]
                    : [
                        'scope' => CouponScope::Target->value,
                        'target_type' => 'App\Models\Course',
                        'target_id' => $row->course_id,
                    ]
            );
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kept on invoice deletion so a campaign's history survives tidying up.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount', 12, 2);
            $table->string('currency', 8);
            $table->string('status', 16);
            $table->timestamp('reserved_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            // The two reads that happen on every redemption: how many uses are
            // live for this coupon, and how many this customer already has.
            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'user_id', 'status']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('coupons')->select('id', 'scope', 'target_type', 'target_id')->get() as $row) {
            if ($row->scope === CouponScope::Target->value && $row->target_type === 'App\Models\Course') {
                DB::table('coupons')->where('id', $row->id)->update(['course_id' => $row->target_id]);
            }
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropMorphs('target');
            $table->dropColumn(['scope', 'starts_at', 'max_uses_per_user', 'min_subtotal', 'description']);
        });
    }
};
