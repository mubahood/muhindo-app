<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Education;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EducationController extends Controller
{
    public function index(): View
    {
        return view('admin.education.index', [
            'items' => Education::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.education.form', ['item' => new Education]);
    }

    public function store(Request $request): RedirectResponse
    {
        Education::create($this->validated($request));

        return redirect()->route('admin.education.index')->with('success', 'Education added.');
    }

    public function edit(Education $education): View
    {
        return view('admin.education.form', ['item' => $education]);
    }

    public function update(Request $request, Education $education): RedirectResponse
    {
        $education->update($this->validated($request));

        return redirect()->route('admin.education.index')->with('success', 'Education updated.');
    }

    public function destroy(Education $education): RedirectResponse
    {
        $education->delete();

        return back()->with('success', 'Education removed.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'institution' => 'required|string|max:150',
            'degree' => 'required|string|max:150',
            'field' => 'nullable|string|max:150',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
