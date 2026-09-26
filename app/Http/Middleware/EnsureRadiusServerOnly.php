<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRadiusServerOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $radius_ip = gethostbyname(config('app.radius_host'));

        if ($request->ip() != $radius_ip) {
            return response()->json([
                'message' => 'Unauthorized request',
            ], 401);
        }

        return $next($request);
    }
}
