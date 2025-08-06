<?php

namespace App\Http\Middleware;

use App\Models\Supplier;
use App\Services\SupplierPortal\SupplierPortalAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SupplierPortalMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the access_key is present in the request
        if ($request->has('access_key')) {
            $accessKey = $request->input('access_key');

            // Manually add the access_key to the request for immediate use
            $request->merge(['supplier_access_key' => $accessKey]);

            // Create the cookie and add it to the response for future requests
            $cookie = cookie('supplier_access_key', $accessKey, 360);
        }
        else if (App::environment('local')) {
            $accessKey = Supplier::query()->first()->access_key;

            $request->merge(['supplier_access_key' => $accessKey]);
            $cookie = cookie('supplier_access_key', $accessKey, 360);
        }
        else {
            // If not present in the request, try getting it from the cookie
            $accessKey = $request->cookie('supplier_access_key');

            // Even if it's retrieved from the cookie, ensure it's in the request for consistent access
            $request->merge(['supplier_access_key' => $accessKey]);
        }

        // Continue with the request
        $response = $next($request);

        // If a new cookie was created, add it to the response
        if (isset($cookie)) {
            $response->cookie($cookie);
        }

        // Access key validation
        if (SupplierPortalAccessService::validateAccessKey($accessKey) === false) {
            abort(401);
        }

        return $response;
    }
}
