<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or ->middleware('role:driver,passenger').
 * Roles: admin, driver, passenger. A user_type of "both" satisfies driver and passenger.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        foreach ($roles as $role) {
            $allowed = match ($role) {
                'admin' => $user->isAdmin(),
                'driver' => $user->isDriver(),
                'passenger' => $user->isPassenger(),
                default => false,
            };

            if ($allowed) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to access this resource.'], 403);
        }

        abort(403, 'You do not have permission to access this area.');
    }
}
