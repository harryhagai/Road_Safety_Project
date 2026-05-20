<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware that filters requests through the RoleMiddleware rule.
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (in_array((string) ($user->role ?? ''), ['admin', 'hgadmin'], true)) {
            return $next($request);
        }

        if ($roles === [] || in_array((string) ($user->role ?? ''), $roles, true)) {
            return $next($request);
        }

        abort(403, 'Unauthorized');
    }
}
