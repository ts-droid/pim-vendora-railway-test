<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function getAmountDue(string $customerNumber): float
    {
        // TODO: Implement this method
        return 0;
    }
}
