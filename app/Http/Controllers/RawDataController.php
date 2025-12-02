<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleFaqEntry;
use Illuminate\Http\Request;

class RawDataController extends Controller
{
    public function article(Request $request)
    {
        $articleNumber = $request->input('article_number', '');

        $article = Article::where('article_number', $articleNumber)->first();
        if (!$article) {
            return response('Article not found', 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $html = self::getArticleRaw($article);

        return view('raw.article', compact('html'));
    }

    public static function getArticleRaw(Article $article, bool $simple = false)
    {
        $faqEntries = ArticleFaqEntry::where('article_id', $article->id)->get();

        $html = '';

        if (!$simple) {
            $html .= '<h1>' . $article->shop_title_sv . '</h1>' . PHP_EOL . PHP_EOL;
            $html .= '<h2>' . $article->shop_marketing_description_sv . '</h2>' . PHP_EOL . PHP_EOL;
        }

        $html .= '<section id="description">' . $article->shop_description_sv . '</section>';

        if ($article->google_product_category) {
            $googleCategories = get_google_product_categories();
            $category = $googleCategories[$article->google_product_category] ?? null;

            if ($category) {
                $html .= '<p>' . $category . '</p>';
            }
        }

        if ($faqEntries) {
            $html .= PHP_EOL . PHP_EOL . '<section id="faq">';

            foreach ($faqEntries as $faqEntry) {
                $html .= '<div>
                            <h3>' . $faqEntry->question_sv . '</h3>
                            <p>' . $faqEntry->answer_sv . '</p>
                          </div>';
            }

            $html .= '</section>';
        }

        return $html;
    }
}
