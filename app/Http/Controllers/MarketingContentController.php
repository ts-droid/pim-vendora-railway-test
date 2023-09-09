<?php

namespace App\Http\Controllers;

use App\Models\ArticleMarketingContent;
use Illuminate\Http\Request;

class MarketingContentController extends Controller
{
    public function articleGet(Request $request)
    {
        $filter = $this->getModelFilter(ArticleMarketingContent::class, $request);

        $query = $this->getQueryWithFilter(ArticleMarketingContent::class, $filter);

        $articleMarketingContents = $query->get();

        return ApiResponseController::success($articleMarketingContents->toArray());
    }

    public function articleStore(Request $request)
    {
        $data = [
            'system' => ($request->system ?? ''),
            'message' => ($request->message ?? ''),
        ];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $language) {
            $data['title_' . $language->language_code] = ($request->{'title_' . $language->language_code} ?? '');
        }

        $articleMarketingContent = ArticleMarketingContent::create($data);

        return ApiResponseController::success($articleMarketingContent->toArray());
    }

    public function articleUpdate(Request $request, ArticleMarketingContent $articleMarketingContent)
    {
        $fillables = get_model_attributes(ArticleMarketingContent::class);

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $articleMarketingContent->{$key} = $value;
            }
        }

        $articleMarketingContent->save();

        return ApiResponseController::success($articleMarketingContent->toArray());
    }

    public function articleDelete(Request $request, ArticleMarketingContent $articleMarketingContent)
    {
        $articleMarketingContent->delete();

        return ApiResponseController::success();
    }
}
