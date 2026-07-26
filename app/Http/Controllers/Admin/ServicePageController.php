<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Manages the "what I do" services grid on the portfolio homepage. */
class ServicePageController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', [
            'items' => Service::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.form', ['item' => new Service]);
    }

    public function store(Request $request): RedirectResponse
    {
        Service::create($this->validated($request));

        return redirect()->route('admin.services.index')->with('success', 'Service added.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.form', ['item' => $service]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('success', 'Service removed.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer',
        ]);
    }
}
