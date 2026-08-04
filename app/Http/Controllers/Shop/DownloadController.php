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
     * How to get this running.
     *
     * A zip of somebody else's codebase is where most source-code sales turn
     * into support requests: it runs on the author's machine and nowhere
     * else. This is the page that closes that gap, and it is behind the
     * licence check because it is part of what was bought.
     */
    public function install(Request $request, Product $product): View
    {
        $license = $request->user()->productLicenses()
            ->where('product_id', $product->id)->first();

        abort_if($license === null, 403, 'You do not own this item.');
        abort_unless($product->hasInstallGuide(), 404, 'This item has no install guide.');

        return view('shop.install', [
            'product' => $product,
            'license' => $license,
            'guide' => app(\App\Services\Learning\MarkdownRenderer::class)->toHtml($product->install_guide),
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
