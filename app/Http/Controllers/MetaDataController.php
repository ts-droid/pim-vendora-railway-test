<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class MetaDataController extends Controller
{
    const BASE_LOCALE = 'en';
    const BASE_LOCALE_TITLE = 'Engelska';

    private OpenAIController $openAIController;

    function __construct()
    {
        $this->openAIController = new OpenAIController();
    }

    public function processArticles()
    {
        // Generate meta titles
        $articles = $this->getArticles('meta_title');

        if ($articles) {
            foreach ($articles as $article) {
                $this->generateTitleForArticle($article);
            }
        }


        // Generate meta descriptions
        $articles = $this->getArticles('meta_description');

        if ($articles) {
            foreach ($articles as $article) {
                $this->generateDescriptionForArticle($article);
            }
        }
    }

    public function generateTitleForArticle(Article $article)
    {
        // Construct the prompt
        $system = 'NAME: ' . $article->{'shop_title_' . self::BASE_LOCALE} . PHP_EOL . PHP_EOL .
            'DESCRIPTION: ' . $article->{'shop_description_' . self::BASE_LOCALE};

        $message = 'Läs igenom NAMN och BESKRIVNING och skriv en meta titel för produkten i ett SEO syfte.
        Meta titeln ska vara mellan 30 och 60 tecken.
        Skriv meta-titeln på ' . self::BASE_LOCALE_TITLE . '.';

        // Send prompt to OpenAI
        $response = $this->openAIController->chatCompletionWithTranslations($system, $message, self::BASE_LOCALE);

        // Save the title to the product
        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            $value = $response[$locale->language_code] ?? '';

            if (strlen($value) > 250) {
                continue;
            }

            $article->{'meta_title_' . $locale->language_code} = $value;
        }

        $article->save();
    }

    public function generateDescriptionForArticle(Article $article)
    {
        // Construct the prompt
        $system = 'NAMN: ' . $article->{'shop_title_' . self::BASE_LOCALE} . PHP_EOL . PHP_EOL .
                    'BESKRIVNING: ' . $article->{'shop_description_' . self::BASE_LOCALE};

        $message = 'Läs igenom NAMN och BESKRIVNING och skriv en meta description för produkten i ett SEO syfte.
        Meta beskrivningen får vara maximal 160 tecken lång. Du får absolut INTE använda mer än 160 tecken.
        Skriv meta-beskrivningen på ' . self::BASE_LOCALE_TITLE . '.';

        // Send prompt to OpenAI
        $response = $this->openAIController->chatCompletionWithTranslations($system, $message, self::BASE_LOCALE);

        // Save the description to the product
        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $locale) {
            $article->{'meta_description_' . $locale->language_code} = $response[$locale->language_code] ?? '';
        }

        $article->save();
    }

    private function getArticles(string $column)
    {
        return Article::where(function($query) use ($column) {
                $query->where($column . '_' . self::BASE_LOCALE, '=', '')
                    ->orWhereNull($column . '_' . self::BASE_LOCALE);
            })
            ->where('shop_title_' . self::BASE_LOCALE, '!=', '')
            ->whereNotNull('shop_title_' . self::BASE_LOCALE)
            ->where('shop_description_' . self::BASE_LOCALE, '!=', '')
            ->whereNotNull('shop_description_' . self::BASE_LOCALE)
            ->limit(10)
            ->get();
    }
}
