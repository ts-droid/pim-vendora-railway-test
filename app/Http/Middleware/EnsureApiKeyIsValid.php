<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ApiResponseController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->get('api_key', '');

        $apiKeyController = new ApiKeyController();

        if (!$apiKeyController->validateKey($apiKey) && !App::environment('local')) {
            return ApiResponseController::error('Invalid API key.');
        }

        return $next($request);
    }
}
