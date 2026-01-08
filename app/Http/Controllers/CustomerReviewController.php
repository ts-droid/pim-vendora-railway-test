<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CustomerReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CustomerReviewController extends Controller
{
    public function index(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleID = $request->get('article_id', 0);
        $rating = $request->get('rating', 5);
        $lang = $request->get('lang', 'en');

        $article = Article::findOrFail($articleID);

        App::setLocale($lang);

        return view('customerReviews.review', compact('article', 'rating', 'lang'));
    }

    public function submit(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $articleID = $request->get('article_id', 0);
        $lang = $request->get('lang', 'en');

        $article = Article::findOrFail($articleID);

        $rating = (int) $request->input('rating', 5);
        $name = trim((string) $request->input('name', ''));
        $review = trim((string) $request->input('review', ''));
        $locale = (string) ($request->input('locale') ?: 'en');

        if (!$rating || !$name) {
            return redirect()->back();
        }

        CustomerReview::create([
            'article_number' => $article->article_number,
            'rating' => $rating,
            'name' => $name,
            'review' => $review,
            'locale' => $locale
        ]);

        return redirect()->route('customer.review.done', ['lang' => $lang]);
    }

    public function done(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $lang = $request->get('lang', 'en');
        App::setLocale($lang);

        return view('customerReviews.done');
    }
}
