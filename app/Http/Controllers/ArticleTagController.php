<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleTagController extends Controller
{
    public function get()
    {
        $articleTags = ArticleTag::orderBy('title_en', 'ASC')->get();

        return ApiResponseController::success($articleTags->toArray());
    }

    public function getTag(ArticleTag $articleTag)
    {
        $articleTag->article_ids = DB::table('article_tag_connections')
            ->where('article_tag_id', $articleTag->id)
            ->pluck('article_id')
            ->toArray();

        return ApiResponseController::success($articleTag->toArray());
    }

    public function store(Request $request)
    {
        $data = [];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $language) {
            $data['title_' . $language->language_code] = (string) $request->input('title_' . $language->language_code);
        }

        $articleTag = ArticleTag::create($data);

        return ApiResponseController::success($articleTag->toArray());
    }

    public function update(Request $request, ArticleTag $articleTag)
    {
        $articleTag->update($request->all());

        return ApiResponseController::success($articleTag->toArray());
    }

    public function delete(Request $request, ArticleTag $articleTag)
    {
        DB::table('article_tag_connections')
            ->where('article_tag_id', $articleTag->id)
            ->delete();

        $articleTag->delete();

        return ApiResponseController::success();
    }

    public function connect(Request $request, ArticleTag $articleTag, Article $article)
    {
        DB::table('article_tag_connections')->insert([
            'article_id' => $article->id,
            'article_tag_id' => $articleTag->id,
        ]);

        return ApiResponseController::success();
    }

    public function disconnect(ArticleTag $articleTag, Article $article)
    {
        DB::table('article_tag_connections')
            ->where('article_id', $article->id)
            ->where('article_tag_id', $articleTag->id)
            ->delete();

        return ApiResponseController::success();
    }
}
