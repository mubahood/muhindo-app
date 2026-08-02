<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /** Everything this person owns, in one place. */
    public function index(Request $request): View
    {
        return view('shop.downloads', [
            'licenses' => $request->user()->productLicenses()
                ->with('product')->latest('granted_at')->get(),
            'enrollments' => $request->user()->enrollments()
                ->with('course')->whereIn('status', ['active', 'completed'])->latest()->get(),
        ]);
    }

    /**
     * Serve a purchased file.
     *
     * The licence is looked up from the signed-in user rather than taken from
     * the URL, so guessing another person's licence id gets you nothing. Files
     * live outside the public disk — the only way to one is through here.
     */
    public function download(Request $request, Product $product): StreamedResponse
    {
        $license = $request->user()->productLicenses()
            ->where('product_id', $product->id)->first();

        abort_if($license === null, 403, 'You do not own this item.');
        abort_if(blank($product->file_path), 404, 'This item has no file attached.');
        abort_unless(Storage::disk('local')->exists($product->file_path), 404, 'The file is missing.');

        $license->increment('download_count');

        return Storage::disk('local')->download(
            $product->file_path,
            $product->file_name ?: basename($product->file_path)
        );
    }
}
