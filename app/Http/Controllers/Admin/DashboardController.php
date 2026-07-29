<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Single dashboard entry point for every role. Sections are composed by
 * capability rather than picking one role view, because an account can be both
 * a student and a client — such a person sees their learning and their projects
 * on the same dashboard.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $svc): View
    {
        $user = $request->user();

        $sections = array_values(array_filter([
            $user->isAdmin() ? 'admin' : null,
            $user->isStudent() ? 'student' : null,
            $user->isClient() ? 'client' : null,
        ]));

        return view('admin.dashboard.index', [
            'user' => $user,
            'svc' => $svc,
            'sections' => $sections !== [] ? $sections : ['fallback'],
        ]);
    }

    /** §3.4 — dismisses the first-visit onboarding checklist card, remembered per user. */
    public function dismissOnboarding(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_dismissed_at' => now()]);

        return redirect()->route('dashboard');
    }
}
