<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Serves the Blade-based Priskalkylator UI. The page itself is rendered
 * server-side with an initial state, and Alpine re-calls the JSON API
 * (/api/v1/price-calculator/*) for live slider updates.
 *
 * Auth: uses the same `?api_key=...` pattern as the public PO links.
 * TODO: hook into the admin session/auth when that lands.
 */
class PricingWebController extends Controller
{
    public function __construct(
        private readonly PriceCalculatorService $calculator,
    ) {
    }

    public function calculator(string $articleNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $article = Article::where('article_number', $articleNumber)->first();
        abort_if(!$article, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');

        // Validate the key up-front so slider calls don't fail silently later
        $keyValid = \App\Models\ApiKey::where('api_key', $apiKey)->exists();
        abort_if(!$keyValid, 403, 'Invalid api_key');

        $initial = $this->calculator->initialState($article);

        return View::make('pricing.calculator', [
            'article' => $article,
            'apiKey' => $apiKey,
            'initial' => $initial,
            'calcConfig' => [
                'articleNumber' => $article->article_number,
                'articleName' => $article->description,
                'apiKey' => $apiKey,
                'initial' => $initial,
            ],
        ]);
    }
}
