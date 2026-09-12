<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebAccess
{
    /**
     * Restrict the Blade /admin area to the admin role slug.
     *
     * This is application-area routing (Admin vs React Waiter/Captain),
     * not a substitute for permission checks on individual pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->user()?->role?->slug;

        if ($slug !== 'admin') {
            abort(403, 'You do not have access to the admin portal.');
        }

        return $next($request);
    }
}
