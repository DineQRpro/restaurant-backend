<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCounterWebAccess
{
    /**
     * Restrict the Blade /counter area to the counter role slug.
     *
     * This is application-area routing (Counter vs Admin vs React staff),
     * not a substitute for permission checks on individual pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->user()?->role?->slug;

        if ($slug !== 'counter') {
            abort(403, 'You do not have access to the counter portal.');
        }

        return $next($request);
    }
}
