<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Blocks inactive/blocked accounts even if they still hold a session or token. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            if ($request->expectsJson()) {
                $user->currentAccessToken()?->delete();

                return response()->json(['success' => false, 'message' => 'Your account is not active.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Your account is not active.']);
        }

        return $next($request);
    }
}
