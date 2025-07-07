<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleFaqEntry;
use Illuminate\Http\Request;

class RawDataController extends Controller
{
    public function article(Request $request)
    {
        $articleNumber = $request->input('articleNumber', '');

        $article = Article::where('article_number', $articleNumber)->first();
        if (!$article) {
            return response('Article not found', 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $faqEntries = ArticleFaqEntry::where('article_id', $article->id)->get();

        return view('raw.article', compact('article', 'faqEntries'));
    }
}
