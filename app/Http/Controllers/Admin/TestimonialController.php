<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Testimonials are quotes attributed to named people, so they get a real
 * editing screen rather than living as hand-edited JSON. They stay in settings
 * rather than a table because the home page reads the whole set at once and
 * there will only ever be a handful.
 */
class TestimonialController extends Controller
{
    public function index(): View
    {
        return view('admin.testimonials.index', ['items' => $this->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // A reference is useful with just a name and a verifiable link; the
            // quote arrives whenever that person actually sends their words.
            'quote' => 'nullable|string|max:600',
            'name' => 'required|string|max:120',
            'role' => 'nullable|string|max:120',
            'org' => 'nullable|string|max:120',
            'link' => 'nullable|url|max:300',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $entry = [
            'quote' => $data['quote'] ?? null,
            'name' => $data['name'],
            'role' => $data['role'] ?? '',
            'org' => $data['org'] ?? '',
            'link' => $data['link'] ?? null,
            'photo' => $request->hasFile('photo')
                ? 'storage/'.$request->file('photo')->store('testimonials', 'public')
                : null,
        ];

        $this->save([...$this->all(), $entry]);

        return back()->with('success', 'Testimonial added.');
    }

    /**
     * Edit one in place.
     *
     * Without this the only way to fix a typo in somebody's name or title was
     * to delete the entry and retype it, which also destroyed their photo,
     * for a spelling mistake. The photo is kept unless a new file is supplied.
     */
    public function update(Request $request, int $index): RedirectResponse
    {
        $items = $this->all();

        if (! array_key_exists($index, $items)) {
            return back()->with('error', 'That testimonial no longer exists.');
        }

        $data = $request->validate([
            'quote' => 'nullable|string|max:600',
            'name' => 'required|string|max:120',
            'role' => 'nullable|string|max:120',
            'org' => 'nullable|string|max:120',
            'link' => 'nullable|url|max:300',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_photo' => 'nullable|boolean',
        ]);

        $existing = $items[$index];
        $photo = $existing['photo'] ?? null;

        if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
            // Only delete what we put there. A path outside storage/ was
            // committed to the repo by hand and is not ours to remove.
            if ($photo && str_starts_with($photo, 'storage/')) {
                Storage::disk('public')->delete(substr($photo, strlen('storage/')));
            }
            $photo = null;
        }

        if ($request->hasFile('photo')) {
            $photo = 'storage/'.$request->file('photo')->store('testimonials', 'public');
        }

        $items[$index] = [
            'quote' => $data['quote'] ?? null,
            'name' => $data['name'],
            'role' => $data['role'] ?? '',
            'org' => $data['org'] ?? '',
            'link' => $data['link'] ?? null,
            'photo' => $photo,
        ];

        // No array_values here: replacing an element leaves the list intact.
        // destroy() needs it because unset() punches a hole in the keys.
        $this->save($items);

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroy(int $index): RedirectResponse
    {
        $items = $this->all();

        if (! array_key_exists($index, $items)) {
            return back()->with('error', 'That testimonial no longer exists.');
        }

        $photo = $items[$index]['photo'] ?? null;
        if ($photo && str_starts_with($photo, 'storage/')) {
            Storage::disk('public')->delete(substr($photo, strlen('storage/')));
        }

        unset($items[$index]);
        $this->save(array_values($items));

        return back()->with('success', 'Testimonial removed.');
    }

    /** @return list<array<string,mixed>> */
    private function all(): array
    {
        $raw = Settings::get('portfolio.testimonials');

        return $raw ? (json_decode($raw, true) ?: []) : [];
    }

    /**
     * Callers re-index before calling, so the stored JSON is always a plain
     * array. A hole in the keys would serialise as an object and break the
     * home page's iteration.
     *
     * @param  list<array<string,mixed>>  $items
     */
    private function save(array $items): void
    {
        Settings::set('portfolio.testimonials', json_encode($items));
    }
}
