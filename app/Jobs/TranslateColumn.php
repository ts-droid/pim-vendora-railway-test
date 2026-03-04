<?php

namespace App\Jobs;

use App\Http\Controllers\TranslationController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class TranslateColumn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $table,
        public int $rowId,
        public string $sourceColumn,
        public string $targetColumn
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sourceValue = (string) DB::table($this->table)
            ->select($this->sourceColumn)
            ->where('id', $this->rowId)
            ->value($this->sourceColumn);

        if (!$sourceValue) return;

        $sourceLang = substr($this->sourceColumn, -2);
        $targetLang = substr($this->targetColumn, -2);

        $translationController = new TranslationController();
        list($translations) = $translationController->translate([$sourceValue], $sourceLang, $targetLang);

        if (count($translations) === 0) return;

        DB::table($this->table)
            ->where('id', $this->rowId)
            ->update([$this->targetColumn => $translations[0]]);
    }
}
