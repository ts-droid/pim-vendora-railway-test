<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GzipMiddleware
{
    private array $except = [
        '/api/v1/marketing-content/article',
        '/api/v1/marketing-content/blog-post'
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

        $response = $next($request);
        $content = $response->content();
        $data = gzencode($content, 9);

        return response($data)->withHeaders([
            'Content-type' => 'application/json; charset=utf-8',
            'Content-Length'=> strlen($data),
            'Content-Encoding' => 'gzip'
        ]);
    }
}
