<?php

namespace App\Jobs;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticleMetaData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const DEFAULT_LANGUAGE = 'en';

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $articleID
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $languages = (new LanguageController())->getAllLanguages();

        // Load the article
        $article = Article::where('id', $this->articleID)->first();
        if (!$article) {
            return;
        }

        $promptController = new PromptController();
        $translationController = new TranslationController();

        // Fetch prompts based on system_code
        $metaTitlePrompt = $promptController->getBySystemCode('article_meta_title');
        $metaDescriptionPrompt = $promptController->getBySystemCode('article_meta_description');

        $articleUpdate = [];

        // Generate and translate meta title
        if ($metaTitlePrompt) {
            $metaTitle = $promptController->execute($metaTitlePrompt->id, [
                'title' => $article->{'shop_title_' . self::DEFAULT_LANGUAGE},
                'description' => $article->{'shop_description_' . self::DEFAULT_LANGUAGE},
            ]);

            $articleUpdate['meta_title_' . self::DEFAULT_LANGUAGE] = $metaTitle;

            foreach ($languages as $language) {
                if ($language->language_code == self::DEFAULT_LANGUAGE) {
                    continue;
                }

                list($translation) = $translationController->translate([$metaTitle], self::DEFAULT_LANGUAGE, $language->language_code);
                $articleUpdate['meta_title_' . $language->language_code] = $translation;
            }
        }

        // Generate and translate meta description
        if ($metaDescriptionPrompt) {
            $metaDescription = $promptController->execute($metaDescriptionPrompt->id, [
                'title' => $article->{'shop_title_' . self::DEFAULT_LANGUAGE},
                'description' => $article->{'shop_description_' . self::DEFAULT_LANGUAGE},
            ]);

            $articleUpdate['meta_description_' . self::DEFAULT_LANGUAGE] = $metaDescription;

            foreach ($languages as $language) {
                if ($language->language_code == self::DEFAULT_LANGUAGE) {
                    continue;
                }

                list($translation) = $translationController->translate([$metaDescription], self::DEFAULT_LANGUAGE, $language->language_code);
                $articleUpdate['meta_description_' . $language->language_code] = $translation;
            }
        }

        // Update the article in the database
        $article->update($articleUpdate);
    }
}
