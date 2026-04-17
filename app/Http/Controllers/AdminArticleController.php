<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ApiKey;
use App\Models\Brand;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin article detail page for demo / Railway test.
 *
 * The real admin UI lives in the adm.vendora.se SPA (separate codebase).
 * This controller exists only to show how the Pricing calculator would look
 * when embedded as one of the tabs (General, Logistics, Web, Images, Files,
 * Reviews, Campaign, Google, RAW, FAQ, Outlet, Design for / use cases,
 * Pricing). Only General and Pricing have real content — the other tabs
 * render a "Mocked — lives in adm.vendora.se" placeholder.
 */
class AdminArticleController extends Controller
{
    public function __construct(
        private readonly PriceCalculatorService $calculator,
    ) {
    }

    public function show(string $articleNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $article = Article::where('article_number', $articleNumber)->first();
        abort_if(!$article, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $tab = $request->input('tab', 'general');
        // Tab order matches the adm.vendora.se admin screen, but with Pricing
        // inserted right after General (user's preferred placement).
        $allowedTabs = ['general', 'pricing', 'logistics', 'web', 'images', 'files',
            'reviews', 'campaign', 'google', 'raw', 'faq', 'outlet', 'design'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        $initial = $this->calculator->initialState($article);

        // Resolve brand-level defaults so the Pricing tab can show the
        // real cascade (article override → brand default → global).
        $brand = $article->brand
            ? Brand::where('name', $article->brand)->first()
            : null;

        return View::make('admin.article', [
            'article' => $article,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'tabs' => $allowedTabs,
            'initial' => $initial,
            'brand' => $brand,
            'calcConfig' => [
                'articleNumber' => $article->article_number,
                'articleName' => $article->description,
                'apiKey' => $apiKey,
                'initial' => $initial,
            ],
        ]);
    }
}
