<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private function authorizeManage(): void
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);
    }

    public function index()
    {
        $this->authorizeManage();

        return view('admin.settings.index', ['settings' => Settings::all()]);
    }

    public function update(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['required', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'default_theme' => ['required', 'in:light,dark'],
        ]);

        foreach ($data as $key => $value) {
            Settings::set($key, $value);
        }

        return back()->with('success', 'Settings saved.');
    }
}
