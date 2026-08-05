<?php

namespace App\Http\Middleware;

use App\Support\AfterAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A client's portal is empty until they have said what they want built.
 *
 * Without this a client can reach the portal by typing the URL, by an old
 * bookmark, or by the header, and find nothing there and no obvious next
 * step. The redirect is not a wall. It is the missing first page.
 */
class ClientMustPropose
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AfterAuth::mustPropose($request->user())) {
            return redirect()->route('propose')->with('success',
                'One thing first, tell me what you want built and it starts moving.');
        }

        return $next($request);
    }
}
