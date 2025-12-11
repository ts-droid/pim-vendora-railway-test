<?php

namespace App\Utilities;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class ArticleTitleUtility
{
    public static function getTitle(int|Article $article, string $locale, bool $includeColor = true): string
    {
        if (is_int($article)) {
            $article = Article::find($article);
        }

        if (!$article) return '';

        $color = $article->{'color_' . $locale} ?? '';

        $title = DB::table('article_titles')
            ->where('article_id', $article->id)
            ->where('locale', $locale)
            ->value('title');

        if (!$title) {
            $title = $article->description;
        }

        if ($includeColor) {
            return $title . ($color ? (' - ' . $color) : '');
        } else {
            return $title;
        }
    }

    public static function setTitle(Article $article, string $title, string $locale): void
    {
        DB::table('article_titles')
            ->where('article_id', $article->id)
            ->where('locale', $locale)
            ->delete();

        DB::table('article_titles')->insert([
            'article_id' => $article->id,
            'title' => $title,
            'locale' => $locale
        ]);
    }

    public static function translateTitles(int|Article $article): void
    {
        if (is_int($article)) {
            $article = Article::find($article);
        }

        $articleID = (int) $article->id;
        $title = (string) $article->description;

        if (!$articleID || !$title) return;

        self::resetTitles($article);

        $translationController = new TranslationController();

        foreach ((new LanguageController())->getAllLanguages() as $language) {
            if ($language->language_code == 'en') continue;

            $translations = $translationController->translate([$title], 'en', $language->language_code);
            $translatedTitle = $translations[0] ?? '';

            if ($translatedTitle) {
                DB::table('article_titles')->insert([
                    'article_id' => $articleID,
                    'title' => $translatedTitle,
                    'locale' => $language->language_code
                ]);
            }
        }
    }

    private static function resetTitles(int|Article $article): void
    {
        if (is_int($article)) {
            $articleID = Article::find($article)->value('id');
        } else {
            $articleID = $article->id;
        }

        if (!$article) return;

        // Remove all generated titles
        DB::table('article_titles')->where('article_id', $articleID)->delete();
    }
}
