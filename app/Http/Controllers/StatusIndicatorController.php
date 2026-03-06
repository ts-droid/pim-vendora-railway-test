<?php

namespace App\Http\Controllers;

use App\Models\StatusIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusIndicatorController extends Controller
{
    /**
     * Pings a status indicator.
     *
     * @param string $title
     * @param int $validForSeconds
     * @return void
     */
    public static function ping(string $title, int $validForSeconds)
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        $statusIndicator = StatusIndicator::where('title', $title)->first();

        if (!$statusIndicator) {
            $statusIndicator = new StatusIndicator();
        }

        $statusIndicator->title = $title;
        $statusIndicator->ping_time = time();
        $statusIndicator->ping_expires = time() + $validForSeconds;
        $statusIndicator->save();
    }

    public function pingRequest(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $title = $request->get('title');
        $validForSeconds = (int) $request->get('valid_for_seconds');

        if (!$title) {
            return ApiResponseController::error('Missing parameter "title".');
        }
        if (!$validForSeconds) {
            return ApiResponseController::error('Missing parameter "valid_for_seconds".');
        }

        self::ping($title, $validForSeconds);

        return ApiResponseController::success([]);
    }

    public function getAll()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $statusIndicators = StatusIndicator::orderBy('title', 'ASC')->get();

        return ApiResponseController::success($statusIndicators->toArray());
    }

    public function getArticleStatus()
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $counts = Cache::remember('getArticleStatus', 300, function () {
            return [
                'shop_title' => $this->getColumnCount('shop_title',  true),
                'shop_description' => $this->getColumnCount('shop_description',  true),
                'shop_marketing_description' => $this->getColumnCount('shop_marketing_description', true),
                'short_description' => $this->getColumnCount('short_description', true),
                'category' => $this->getColumnCount('category_ids', false),
                'meta_title' => $this->getColumnCount('meta_title', true),
                'meta_description' => $this->getColumnCount('meta_description', true),
                'google_category' => $this->getColumnCount('google_product_category', false)
            ];
        });

        return ApiResponseController::success($counts);
    }

    private function getColumnCount(string $column, bool $useLocales)
    {
        $locales = [];
        if ($useLocales) {
            $languages = (new LanguageController())->getAllLanguages();
            $locales = $languages->pluck('language_code');
        }

        return DB::table('articles')
            ->select('id')
            ->where('is_webshop', '1')
            ->whereIn('status', ['Active', 'NoPurchases'])
            ->where(function($query) use ($useLocales, $column, $locales) {
                if ($useLocales) {
                    foreach ($locales as $locale) {
                        $query->orWhere($column . '_' . $locale, '=', '')
                            ->orWhere($column . '_' . $locale, '=', '0')
                            ->orWhereNull($column . '_' . $locale);
                    }
                } else {
                    $query->orWhere($column, '=', '')
                        ->orWhere($column, '=', '0')
                        ->orWhere($column, '=', '[]')
                        ->orWhereNull($column);
                }
            })
            ->count();
    }
}
