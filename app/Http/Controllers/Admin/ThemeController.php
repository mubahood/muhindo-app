<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $theme = $request->validate(['theme' => ['required', 'in:light,dark']])['theme'];
        $request->user()->forceFill(['theme' => $theme])->save();

        return response()->json(['ok' => true, 'theme' => $theme]);
    }
}
