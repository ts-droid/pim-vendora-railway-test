<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    protected int $responseSizeCutoff = 250; // Set the cutoff limit (in KB)

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a unique ID for the request
        $requestID = Str::uuid()->toString();
        $request->headers->set('X-Request-ID', $requestID);


        $startTime = microtime(true);

        // Log request details
        Log::channel('requestlog')->info('Request', [
            'request_id' => $requestID,
            'timestamp' => now()->toDateTimeString(),
            'urk' => $request->fullUrl(),
            'method' => $request->method(),
            'input' => $request->all(),
            'headers' => $request->headers->all(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $response = $next($request);

        $responseTime = microtime(true) - $startTime;

        // Check the size of the response content
        $contentType = (string) $response->headers->get('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $responseSize = strlen($response->getContent()) / 1024; // Size in KB

            if ($responseSize <= $this->responseSizeCutoff) {
                $content = $response->getContent();
                if ($response->headers->get('Content-Encoding') === 'gzip') {
                    $content = gzdecode($content);
                }
            }
            else {
                $content = 'Response too large to log (' . $responseSize . ' KB)';
            }
        }
        else {
            $content = 'Non-JSON content (nog logged)';
        }

        // Log response details
        Log::channel('requestlog')->info('Response', [
            'request_id' => $requestID,
            'timestamp' => now()->toDateTimeString(),
            'status' => $response->status() ?? '',
            'status_code' => $response->getStatusCode() ?? '',
            'content' => $content,
            'response_time' => $responseTime,
        ]);

        return $response;
    }
}
