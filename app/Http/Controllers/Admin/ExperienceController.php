<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExperienceController extends Controller
{
    public function index(): View
    {
        return view('admin.experience.index', [
            'items' => Experience::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.experience.form', ['item' => new Experience]);
    }

    public function store(Request $request): RedirectResponse
    {
        Experience::create($this->validated($request));

        return redirect()->route('admin.experience.index')->with('success', 'Experience added.');
    }

    public function edit(Experience $experience): View
    {
        return view('admin.experience.form', ['item' => $experience]);
    }

    public function update(Request $request, Experience $experience): RedirectResponse
    {
        $experience->update($this->validated($request));

        return redirect()->route('admin.experience.index')->with('success', 'Experience updated.');
    }

    public function destroy(Experience $experience): RedirectResponse
    {
        $experience->delete();

        return back()->with('success', 'Experience removed.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company' => 'required|string|max:150',
            'role' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
