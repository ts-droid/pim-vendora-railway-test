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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleTags = ArticleTag::orderBy('title_en', 'ASC')->get();

        return ApiResponseController::success($articleTags->toArray());
    }

    public function getTag(ArticleTag $articleTag)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleTag->article_ids = DB::table('article_tag_connections')
            ->where('article_tag_id', $articleTag->id)
            ->pluck('article_id')
            ->toArray();

        $articleTag->articles = [];

        if ($articleTag->article_ids) {
            $articleTag->articles = Article::whereIn('id', $articleTag->article_ids)->get();
        }

        return ApiResponseController::success($articleTag->toArray());
    }

    public function connections()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $connections = DB::table('article_tag_connections')
            ->join('articles', 'articles.id', '=', 'article_tag_connections.article_id')
            ->select('article_id', 'article_tag_id', 'article_number')
            ->get();

        return ApiResponseController::success($connections->toArray());
    }

    public function store(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleTag->update($request->all());

        return ApiResponseController::success($articleTag->toArray());
    }

    public function delete(Request $request, ArticleTag $articleTag)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        DB::table('article_tag_connections')
            ->where('article_tag_id', $articleTag->id)
            ->delete();

        $articleTag->delete();

        return ApiResponseController::success();
    }

    public function connect(Request $request, ArticleTag $articleTag, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        DB::table('article_tag_connections')->insert([
            'article_id' => $article->id,
            'article_tag_id' => $articleTag->id,
        ]);

        return ApiResponseController::success();
    }

    public function disconnect(ArticleTag $articleTag, Article $article)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        DB::table('article_tag_connections')
            ->where('article_id', $article->id)
            ->where('article_tag_id', $articleTag->id)
            ->delete();

        return ApiResponseController::success();
    }
}
