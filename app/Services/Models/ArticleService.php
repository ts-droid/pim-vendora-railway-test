<?php

namespace App\Services\Models;

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use App\Models\Article;
use App\Services\VismaNet\VismaNetArticleService;

class ArticleService
{
    private Article $article;
    private array $changes;

    private bool $isUpdateShopDescriptionCalled = false;

    public function handleUpdate(Article $article, array $changes): void
    {
        $this->article = $article;
        $this->changes = $changes;

        foreach ($this->changes as $property => $value) {

            switch ($property) {
                default:
                    if (str_starts_with($property, 'shop_description_')) {
                        $this->updateShopDescription();
                    }
                    break;
            }

            // Push the update ti Visma.net
            $vismaNetArticleService = new VismaNetArticleService();
            $vismaNetArticleService->updateArticle($this->article);

        }
    }

    private function updateShopDescription(): void
    {
        if ($this->isUpdateShopDescriptionCalled) {
            return;
        }

        $languages = (new LanguageController())->getAllLanguages();

        // Send update to WGR
        $data = [];

        foreach ($languages as $language) {
            $data['description_' . $language->language_code] = (string) $this->article->{'shop_description_' . $language->language_code};
        }

        $wgrController = new WgrController();
        $wgrController->updateArticle($this->article->article_number, $data);

        $this->isUpdateShopDescriptionCalled = true;
    }
}
