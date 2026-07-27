<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\ProjectInquiry;
use App\Models\User;
use App\Notifications\ProjectInquiryReceivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** §4 — the "Start a project" client funnel: a public page + lead form, distinct from the contact form. */
class ProjectInquiryController extends Controller
{
    public function create(): View
    {
        return view('portfolio.start-a-project', [
            'projects' => PortfolioProject::orderBy('sort_order')->limit(3)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Checked before validation — a `max:0` rule on the honeypot would otherwise
        // reject a bot's non-empty value with a validation error before this branch
        // is ever reached, tipping the bot off instead of silently pretending success.
        if ($request->filled('website')) {
            return redirect()->route('start-a-project')->with('success', "Got it — I'll reply within 24 hours.");
        }

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:32',
            'organisation' => 'nullable|string|max:150',
            'project_type' => 'required|in:website,web_system,mobile_app,ecommerce,school_clinic_system,other',
            'budget_range' => 'nullable|in:under_2m,2m_5m,5m_10m,over_10m,not_sure',
            'timeline' => 'nullable|in:asap,1_3_months,3_6_months,not_sure',
            'description' => 'required|string|max:5000',
        ]);

        $inquiry = ProjectInquiry::create($data + ['uuid' => (string) Str::uuid()]);

        $admins = User::whereIn('role', ['super_admin', 'admin'])->get();
        Notification::send($admins, new ProjectInquiryReceivedNotification($inquiry));

        return redirect()->route('start-a-project')->with('success', "Got it — I'll reply within 24 hours. Prefer a faster reply? Message me on WhatsApp.");
    }
}
