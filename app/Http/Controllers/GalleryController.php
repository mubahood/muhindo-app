<?php

namespace App\Http\Controllers;

use App\Models\GalleryPhoto;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public photo gallery. */
class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->string('category')->trim()->value();

        $photos = GalleryPhoto::published()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return view('gallery.index', [
            'photos' => $photos,
            'categories' => GalleryPhoto::published()->whereNotNull('category')
                ->distinct()->orderBy('category')->pluck('category'),
            'activeCategory' => $category,
        ]);
    }
}
