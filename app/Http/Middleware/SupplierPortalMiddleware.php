<?php

namespace App\Http\Middleware;

use App\Services\SupplierPortal\SupplierPortalAccessService;
use Closure;
use Illuminate\Http\Request;
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
        $response = $next($request);

        if ($request->has('access_key')) {
            $accessKey = $request->input('access_key');

            $cookie = cookie('supplier_access_key', $accessKey, 360);
            $response->cookie($cookie);
        }
        else {
            $accessKey = $request->cookie('supplier_access_key');
        }

        if (SupplierPortalAccessService::validateAccessKey($accessKey) === false) {
            abort(401);
        }

        return $response;
    }
}
