<?php

namespace App\Jobs;

use App\Http\Controllers\VismaNetController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchCustomerInvoicePage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $pageNumber
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vismaController = new VismaNetController();
        $vismaController->fetchCustomerInvoicePage($this->pageNumber);
    }
}
