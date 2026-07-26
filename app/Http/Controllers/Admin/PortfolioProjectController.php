<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortfolioProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.portfolio-projects.index', [
            'projects' => PortfolioProject::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.portfolio-projects.form', ['project' => new PortfolioProject]);
    }

    public function store(Request $request): RedirectResponse
    {
        PortfolioProject::create($this->validated($request));

        return redirect()->route('admin.portfolio-projects.index')->with('success', 'Project created.');
    }

    public function edit(PortfolioProject $portfolioProject): View
    {
        return view('admin.portfolio-projects.form', ['project' => $portfolioProject]);
    }

    public function update(Request $request, PortfolioProject $portfolioProject): RedirectResponse
    {
        $portfolioProject->update($this->validated($request, $portfolioProject));

        return redirect()->route('admin.portfolio-projects.index')->with('success', 'Project updated.');
    }

    public function destroy(PortfolioProject $portfolioProject): RedirectResponse
    {
        $portfolioProject->delete();

        return back()->with('success', 'Project deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?PortfolioProject $project = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => 'nullable|string|max:200|alpha_dash',
            'description' => 'nullable|string',
            'tags' => 'nullable|string',
            'highlights' => 'nullable|string',
            'external_link' => 'nullable|url|max:255',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        $data['tags'] = $this->linesToArray($data['tags'] ?? null);
        $data['highlights'] = $this->linesToArray($data['highlights'] ?? null);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function linesToArray(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode("\n", $value))));
    }
}
