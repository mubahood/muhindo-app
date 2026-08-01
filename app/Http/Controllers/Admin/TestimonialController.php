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
            'quote' => 'required|string|max:600',
            'name' => 'required|string|max:120',
            'role' => 'nullable|string|max:120',
            'org' => 'nullable|string|max:120',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $entry = [
            'quote' => $data['quote'],
            'name' => $data['name'],
            'role' => $data['role'] ?? '',
            'org' => $data['org'] ?? '',
            'photo' => $request->hasFile('photo')
                ? 'storage/'.$request->file('photo')->store('testimonials', 'public')
                : null,
        ];

        $this->save([...$this->all(), $entry]);

        return back()->with('success', 'Testimonial added.');
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
     * array — a hole in the keys would serialise as an object and break the
     * home page's iteration.
     *
     * @param  list<array<string,mixed>>  $items
     */
    private function save(array $items): void
    {
        Settings::set('portfolio.testimonials', json_encode($items));
    }
}
