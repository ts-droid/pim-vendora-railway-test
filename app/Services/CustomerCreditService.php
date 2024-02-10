<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function getAmountDue(string $customerNumber): float
    {
        // TODO: Implement this method
        return 0;
    }

    public function calculateVendoraRating(Customer $customer): void
    {
        // TODO: Implement this method
    }
}
