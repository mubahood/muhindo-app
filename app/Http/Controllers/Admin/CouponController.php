<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Coupon::class);

        return view('admin.coupons.index', [
            'coupons' => Coupon::with('target')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Coupon::class);

        return view('admin.coupons.form', [
            'coupon' => new Coupon(['scope' => CouponScope::Courses]),
        ] + $this->targetChoices());
    }

    /**
     * One coupon's ledger: who used it, on what, and what it gave away.
     *
     * This is the screen a coupon exists for. A code with no answer to "did
     * this campaign make money" is a discount, not a campaign.
     */
    public function show(Coupon $coupon): View
    {
        $this->authorize('view', $coupon);

        return view('admin.coupons.show', [
            'coupon' => $coupon->load('target'),
            'performance' => $coupon->performance(),
            'redemptions' => $coupon->redemptions()
                ->with(['user:id,name,email', 'invoice:id,invoice_no,status,total,currency'])
                ->latest('reserved_at')
                ->paginate(50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Coupon::class);

        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): View
    {
        $this->authorize('update', $coupon);

        return view('admin.coupons.form', ['coupon' => $coupon] + $this->targetChoices());
    }

    /**
     * Both halves of the catalogue, for the single-item scope.
     *
     * @return array{courses: \Illuminate\Support\Collection<int,Course>, products: \Illuminate\Support\Collection<int,Product>}
     */
    private function targetChoices(): array
    {
        return [
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon)],
            'description' => 'nullable|string|max:255',
            'scope' => ['required', Rule::in(array_column(CouponScope::cases(), 'value'))],
            // Only read when the scope is a single item; which of the two is
            // meant is decided by the scope, not by which box was filled.
            'target_course_id' => 'nullable|exists:courses,id',
            'target_product_id' => 'nullable|exists:products,id',
            'type' => ['required', Rule::in(array_column(CouponType::cases(), 'value'))],
            'value' => 'required|numeric|min:0.01',
            'min_subtotal' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $data['code'] = CouponService::normalise($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        if ($data['type'] === CouponType::Percent->value && $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => 'A percent-off coupon cannot exceed 100%.',
            ]);
        }

        [$data['target_type'], $data['target_id']] = $this->targetFor($data);

        unset($data['target_course_id'], $data['target_product_id']);

        return $data;
    }

    /**
     * Resolve the one item a targeted coupon points at.
     *
     * @param  array<string,mixed>  $data
     * @return array{0: ?string, 1: ?int}
     */
    private function targetFor(array $data): array
    {
        if ($data['scope'] !== CouponScope::Target->value) {
            return [null, null];
        }

        if (! empty($data['target_course_id'])) {
            return [(new Course)->getMorphClass(), (int) $data['target_course_id']];
        }

        if (! empty($data['target_product_id'])) {
            return [(new Product)->getMorphClass(), (int) $data['target_product_id']];
        }

        throw ValidationException::withMessages([
            'scope' => 'Choose the course or product this coupon applies to.',
        ]);
    }
}
