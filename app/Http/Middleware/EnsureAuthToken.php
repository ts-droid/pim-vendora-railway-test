<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ApiResponseController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authToken = $request->header('X-API-Key');

        $validToken = User::where('auth_token', $authToken)->exists();
        if (!$validToken) {
            return ApiResponseController::error('Invalid auth token.');
        }

        return $next($request);
    }
}
