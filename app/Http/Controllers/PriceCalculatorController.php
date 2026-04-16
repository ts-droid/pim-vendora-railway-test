<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Http\Request;

/**
 * Admin-facing price calculator (the "Priskalkylator").
 *
 * Intentionally separate from ArticlePriceListController, which serves
 * customer-facing percent-markup pricing. This controller exposes a
 * margin-based calculator UX with live currency conversion and smart
 * rounding — not intended for customer integration.
 */
class PriceCalculatorController extends Controller
{
    public function __construct(
        private readonly PriceCalculatorService $calculator,
    ) {
    }

    public function initialState(string $articleNumber)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $article = Article::where('article_number', $articleNumber)->first();
        if (!$article) {
            return ApiResponseController::error('Article not found: ' . $articleNumber);
        }

        return ApiResponseController::success(
            $this->calculator->initialState($article)
        );
    }

    public function calculate(string $articleNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $article = Article::where('article_number', $articleNumber)->first();
        if (!$article) {
            return ApiResponseController::error('Article not found: ' . $articleNumber);
        }

        $source = $request->input('source');
        if ($source !== null && !in_array($source, ['rrp', 'margin', 'reseller'], true)) {
            return ApiResponseController::error("source must be 'rrp', 'margin', or 'reseller'");
        }

        return ApiResponseController::success(
            $this->calculator->calculate(
                article: $article,
                source: $source,
                rrpExSEK: $request->filled('rrp_ex_sek') ? (float) $request->input('rrp_ex_sek') : null,
                ourMargin: $request->filled('our_margin') ? (float) $request->input('our_margin') : null,
                resellerMargin: $request->filled('reseller_margin') ? (float) $request->input('reseller_margin') : null,
            )
        );
    }
}
