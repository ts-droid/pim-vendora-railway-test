<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrderException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;


class HandlePurchaseOrderExceptions extends Command implements ShouldBeUnique
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase-order-exceptions:handle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle all purchase order exceptions.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $unhandledExceptions = PurchaseOrderException::whereNull('handled_at')->get();

        $groupedExceptions = [];

    }
}
