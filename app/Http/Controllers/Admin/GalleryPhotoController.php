<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

class GalleryPhotoController extends Controller
{
    public function index(): View
    {
        return view('admin.gallery.index', [
            'items' => GalleryPhoto::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.gallery.form', ['item' => new GalleryPhoto]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:12288']);

        $photo = new GalleryPhoto($this->fields($request, $data));
        $this->attach($photo, $request);
        $photo->save();

        return redirect()->route('admin.gallery.index')->with('success', 'Photograph added.');
    }

    public function edit(GalleryPhoto $photo): View
    {
        return view('admin.gallery.form', ['item' => $photo]);
    }

    public function update(Request $request, GalleryPhoto $photo): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12288']);

        $photo->fill($this->fields($request, $data));

        if ($request->hasFile('photo')) {
            $this->deleteFiles($photo);
            $this->attach($photo, $request);
        }

        $photo->save();

        return redirect()->route('admin.gallery.index')->with('success', 'Photograph updated.');
    }

    public function destroy(GalleryPhoto $photo): RedirectResponse
    {
        $this->deleteFiles($photo);
        $photo->delete();

        return back()->with('success', 'Photograph removed.');
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'title' => 'required|string|max:160',
            'caption' => 'nullable|string|max:400',
            'alt' => 'nullable|string|max:250',
            'category' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    /** @return array<string,mixed> */
    private function fields(Request $request, array $data): array
    {
        unset($data['photo']);
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    /**
     * Runs an upload through the same optimisation the bulk importer uses —
     * orientation baked in, metadata stripped, resized, WebP and thumbnail
     * written. An upload that skipped this would be the one 4MB image on an
     * otherwise fast page.
     */
    private function attach(GalleryPhoto $photo, Request $request): void
    {
        $file = $request->file('photo');
        $slug = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6);

        Storage::disk('public')->makeDirectory('gallery/thumbs');
        $jpeg = Storage::disk('public')->path("gallery/{$slug}.jpg");
        $webp = Storage::disk('public')->path("gallery/{$slug}.webp");
        $thumb = Storage::disk('public')->path("gallery/thumbs/{$slug}.jpg");

        $this->convert([$file->getRealPath(), '-auto-orient', '-strip', '-resize', '1600x1600>',
            '-interlace', 'Plane', '-quality', '82', $jpeg]);
        $this->convert([$jpeg, '-quality', '78', $webp]);
        $this->convert([$jpeg, '-resize', '800x800>', '-quality', '78', $thumb]);

        if (! is_file($jpeg)) {
            // ImageMagick missing or the file unreadable — keep the original so
            // the upload is not silently lost.
            $photo->path = $file->store('gallery', 'public');

            return;
        }

        [$width, $height] = getimagesize($jpeg) ?: [null, null];

        $photo->path = "gallery/{$slug}.jpg";
        $photo->webp_path = is_file($webp) ? "gallery/{$slug}.webp" : null;
        $photo->thumb_path = is_file($thumb) ? "gallery/thumbs/{$slug}.jpg" : null;
        $photo->width = $width;
        $photo->height = $height;
        $photo->bytes = (int) filesize($jpeg);
    }

    private function deleteFiles(GalleryPhoto $photo): void
    {
        foreach ([$photo->path, $photo->webp_path, $photo->thumb_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** @param  list<string>  $arguments */
    private function convert(array $arguments): void
    {
        $process = new Process(['magick', ...$arguments], timeout: 120);
        $process->run();
    }
}
