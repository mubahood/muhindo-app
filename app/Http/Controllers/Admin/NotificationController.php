<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The staff member's in-app notification list (database channel). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $note = $request->user()->notifications()->findOrFail($id);
        $note->markAsRead();

        return back()->with('success', 'Marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
