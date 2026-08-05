<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'items' => Product::withCount('licenses')->orderBy('sort_order')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['item' => new Product]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = new Product($this->validated($request));
        $this->attachFiles($request, $product);
        $product->save();

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['item' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->fill($this->validated($request));
        $this->attachFiles($request, $product);
        $product->save();

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->licenses()->exists()) {
            // People who paid for this must keep their download.
            return back()->with('error', 'This product has been bought, unpublish it instead of deleting it.');
        }

        if ($product->cover_image) {
            Storage::disk('public')->delete($product->cover_image);
        }
        if ($product->file_path) {
            Storage::disk('local')->delete($product->file_path);
        }

        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'slug' => 'nullable|string|max:200|alpha_dash',
            'type' => 'required|in:'.implode(',', array_keys(Product::TYPES)),
            'summary' => 'nullable|string|max:300',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:80',
            'tags' => 'nullable|string|max:250',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0|gte:price',
            'currency' => 'required|string|size:3',
            'external_url' => 'nullable|url|max:400',
            'sort_order' => 'nullable|integer|min:0',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'file' => 'nullable|file|max:102400',
        ]);

        $data['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))));
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        unset($data['cover'], $data['file']);

        return $data;
    }

    private function attachFiles(Request $request, Product $product): void
    {
        if ($request->hasFile('cover')) {
            if ($product->cover_image) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $product->cover_image = $request->file('cover')->store('products', 'public');
        }

        if ($request->hasFile('file')) {
            if ($product->file_path) {
                Storage::disk('local')->delete($product->file_path);
            }

            $file = $request->file('file');
            // Stored on the private disk: a paid deliverable must never be
            // reachable by URL, only through the licence check.
            $product->file_path = $file->store('products', 'local');
            $product->file_name = $file->getClientOriginalName();
            $product->file_bytes = $file->getSize();
        }
    }
}
