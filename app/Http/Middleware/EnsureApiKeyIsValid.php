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
    private array $except = [
        '/api/v1/marketing-content/article',
        '/api/v1/marketing-content/blog-post',
        '/api/v1/marketing-content/review-post'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->except as $except) {
            if (str_starts_with(('/' . $request->path()), $except)) {
                return $next($request);
            }
        }

        $apiKey = (string) $request->get('api_key', '');

        $apiKeyController = new ApiKeyController();

        if (!$apiKeyController->validateKey($apiKey) && !App::environment('local')) {
            return ApiResponseController::error('Invalid API key.');
        }

        return $next($request);
    }
}
