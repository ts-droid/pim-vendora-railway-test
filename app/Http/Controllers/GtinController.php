<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\GS1\Gs1ValidooService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * GS1 Sweden (Validoo) GTIN operations.
 *
 * All endpoints use the /api/v1 middleware stack (api.key + gzip).
 * Not auto-triggered — auto-GTIN on bundle creation lands in a later PR.
 */
class GtinController extends Controller
{
    public function __construct(
        private readonly Gs1ValidooService $gs1,
    ) {
    }

    public function status()
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        return ApiResponseController::success([
            'configured' => $this->gs1->isConfigured(),
            'company_prefix' => $this->gs1->companyPrefix() ?: null,
        ]);
    }

    public function generate(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $amount = max(1, min(1000, (int) $request->input('amount', 1)));

        try {
            $keys = $this->gs1->generateGTIN($amount);
            return ApiResponseController::success(['keys' => $keys]);
        } catch (RuntimeException $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }

    public function activate(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $gtin = (string) $request->input('gtin', '');
        $productName = (string) $request->input('productName', '');
        $brandName = $request->input('brandName');
        $status = (string) $request->input('status', 'DRAFT');

        if (!$gtin) {
            return ApiResponseController::error('gtin required');
        }
        if (!in_array($status, ['ACTIVE', 'DRAFT', 'INACTIVE'], true)) {
            return ApiResponseController::error('status must be ACTIVE, DRAFT, or INACTIVE');
        }

        try {
            $batchId = $this->gs1->activateGTIN($gtin, $productName, $brandName, $status);
            return ApiResponseController::success(['batchId' => $batchId]);
        } catch (RuntimeException $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }

    /**
     * Generate a GTIN and store it on an article's `ean` column. Useful for
     * bundles that were created without an EAN.
     */
    public function generateForArticle(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $articleNumber = (string) $request->input('article_number', '');
        if (!$articleNumber) {
            return ApiResponseController::error('article_number required');
        }

        $article = Article::where('article_number', $articleNumber)->first();
        if (!$article) {
            return ApiResponseController::error('Article not found');
        }

        try {
            $gtin = $this->gs1->generateAndActivate($article->description, null);
            $article->ean = $gtin;
            $article->save();

            return ApiResponseController::success([
                'article_number' => $articleNumber,
                'gtin' => $gtin,
            ]);
        } catch (RuntimeException $e) {
            return ApiResponseController::error($e->getMessage());
        }
    }
}
