<?php

namespace App\Jobs;

use App\Http\Controllers\LanguageController;
use App\Models\Language;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SetupLanguage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Language $language
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $languageController = new LanguageController();

        $languageController->setupLanguageColumns($this->language->language_code);

        Artisan::call('wgr:fetch', [
            'type' => 'all',
            'skipImages' => 1
        ]);
    }
}
