<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $type = $request->string('type')->trim()->value();

        $products = Product::published()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($request->string('sort')->value() === 'price', fn ($q) => $q->orderBy('price'))
            ->when($request->string('sort')->value() === 'popular', fn ($q) => $q->orderByDesc('purchases_count'))
            ->orderBy('sort_order')->orderByDesc('id')
            ->paginate(9)->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'categories' => Product::published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'types' => Product::published()->distinct()->orderBy('type')->pluck('type'),
            'filters' => ['q' => $search, 'category' => $category, 'type' => $type, 'sort' => $request->string('sort')->value()],
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_published, 404);

        return view('shop.show', [
            'product' => $product,
            'owned' => (bool) auth()->user()?->productLicenses()->where('product_id', $product->id)->exists(),
            'related' => Product::published()->whereKeyNot($product->id)
                ->where('category', $product->category)->limit(3)->get(),
        ]);
    }
}
