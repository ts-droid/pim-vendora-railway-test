<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class PulseAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIPs = [
            '95.143.200.80',
            '213.21.125.167' // Majoren FC
        ];

        if (!App::isLocal() && !in_array(get_user_ip(), $allowedIPs)) {
            abort(403);
        }

        return $next($request);
    }
}
