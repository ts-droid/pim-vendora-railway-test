<?php

namespace App\Http\Middleware;

use App\Services\TranslationServiceManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TranslationEngine
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $translationService = $request->input('translation_service');
        if (!$translationService) {
            return $next($request);
        }

        $manager = new TranslationServiceManager();
        $services = $manager->getAllServices();

        foreach ($services as $service) {
            if ($service->name == $translationService) {
                Cache::store('array')->put('translation_service', $service);
            }
        }

        return $next($request);
    }
}
