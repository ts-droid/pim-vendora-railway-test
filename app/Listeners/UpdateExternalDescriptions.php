<?php

namespace App\Listeners;

use App\Events\ArticleUpdated;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\WgrController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateExternalDescriptions
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ArticleUpdated $event): void
    {
        $updated = false;

        foreach (LanguageController::SUPPORTED_LANGUAGES as $language) {
            if (isset($event->changes['shop_description_' . $language])) {
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return;
        }

        $this->updateWGR($event);
    }

    /**
     * Updates the description in WGR API
     *
     * @param ArticleUpdated $event
     * @return void
     */
    private function updateWGR(ArticleUpdated $event): void
    {
        $wgrController = new WgrController();

        $data = [];

        foreach (LanguageController::SUPPORTED_LANGUAGES as $language) {
            $data['description_' . $language] = (string) $event->article->{'shop_description_' . $language};
        }

        $wgrController->updateArticle($event->article->article_number, $data);
    }
}
