<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Product;
use App\Services\Shop\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart) {}

    public function show(): View
    {
        return view('shop.cart', [
            'lines' => $this->cart->contents(),
            'subtotal' => $this->cart->subtotal(),
            'currency' => $this->cart->currency() ?? 'UGX',
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:product,course',
            'id' => 'required|integer',
            'quantity' => 'nullable|integer|min:1|max:99',
            // "Buy now" is the same add, followed by going straight to checkout.
            'buy_now' => 'nullable|boolean',
        ]);

        $item = $data['type'] === 'product'
            ? Product::published()->find($data['id'])
            : Course::where('is_published', true)->find($data['id']);

        if ($item === null) {
            return back()->with('error', 'That item is no longer available.');
        }

        /* The first of three checks on the same fact, at the three points
           money can start moving. Taking payment for a file that is not there
           is the single most damaging thing this shop could do, so the answer
           is not trusted from one place. */
        if ($item instanceof Product && ! $item->isDeliverable()) {
            return back()->with('error',
                'That download is not ready yet — it has not been added to the basket.');
        }

        $this->cart->add($item, (int) ($data['quantity'] ?? 1));

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.review');
        }

        return back()->with('success', 'Added to your basket.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:60',
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $this->cart->setQuantity($data['key'], (int) $data['quantity']);

        return back()->with('success', 'Basket updated.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $this->cart->remove($request->validate(['key' => 'required|string|max:60'])['key']);

        return back()->with('success', 'Removed from your basket.');
    }
}
