<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Single dashboard entry point for every role. Picks a per-role view; each
 * view composes widgets fed by DashboardService.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $svc): View
    {
        $user = $request->user();

        $role = match (true) {
            $user->isAdmin() => 'admin',
            $user->isStudent() => 'student',
            $user->isClient() => 'client',
            default => 'fallback',
        };

        return view('admin.dashboard.index', [
            'user' => $user,
            'svc' => $svc,
            'role' => $role,
        ]);
    }
}
