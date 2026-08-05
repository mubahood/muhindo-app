<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\ProjectInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The "Start a project" lead inbox. */
class ProjectInquiryController extends Controller
{
    public function index(): View
    {
        return view('admin.project-inquiries.index', [
            'inquiries' => ProjectInquiry::latest()->get(),
        ]);
    }

    public function show(ProjectInquiry $projectInquiry): View
    {
        return view('admin.project-inquiries.show', [
            'inquiry' => $projectInquiry,
        ]);
    }

    public function updateStatus(Request $request, ProjectInquiry $projectInquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(ProjectInquiryStatus::cases(), 'value'))],
        ]);

        $projectInquiry->update($data);

        return back()->with('success', 'Status updated.');
    }

    /** Delete an inquiry, spam gets through, and it had nowhere to go. */
    public function destroy(\App\Models\ProjectInquiry $projectInquiry): \Illuminate\Http\RedirectResponse
    {
        $projectInquiry->delete();

        return redirect()->route('admin.project-inquiries.index')->with('success', 'Inquiry deleted.');
    }
}
