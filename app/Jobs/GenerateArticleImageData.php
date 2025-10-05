<?php

namespace App\Jobs;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\TranslationController;
use App\Models\Article;
use App\Models\ArticleImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticleImageData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const DEFAULT_LANGUAGE = 'sv';

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $imageID
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        return;

        $languages = (new LanguageController())->getAllLanguages();

        // Load the image
        $image = ArticleImage::where('id', $this->imageID)->first();
        if (!$image) {
            return;
        }

        // Load associated article
        $article = Article::where('id', $image->article_id)->first();
        if (!$article) {
            return;
        }

        $promptController = new PromptController();
        $translationController = new TranslationController();

        // Fetch the prompt based on system_code
        $prompt = $promptController->getBySystemCode('article_image_alt_product');

        $imageUpdate = [];

        // Generate and translate alt text
        if ($prompt) {
            $altText = $promptController->execute($prompt->id, [
                'title' => $article->{'shop_title_' . self::DEFAULT_LANGUAGE},
                'description' => $article->{'shop_description_' . self::DEFAULT_LANGUAGE},
            ]);

            $altText = remove_quotations($altText);

            $imageUpdate['alt_text_' . self::DEFAULT_LANGUAGE] = $altText;

            foreach ($languages as $language) {
                if ($language->language_code == self::DEFAULT_LANGUAGE) {
                    continue;
                }

                list($translation) = $translationController->translate([$altText], self::DEFAULT_LANGUAGE, $language->language_code);
                $imageUpdate['alt_text_' . $language->language_code] = $translation;
            }
        }

        // Update the image in the database
        $image->update($imageUpdate);
    }
}
