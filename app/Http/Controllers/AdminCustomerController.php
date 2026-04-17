<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin customer detail page. Same pattern as
 * AdminSupplierController — read-only view over imported production data.
 */
class AdminCustomerController extends Controller
{
    public function show(string $customerNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $customer = Customer::where('customer_number', $customerNumber)->first();
        abort_if(!$customer, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $tab = $request->input('tab', 'general');
        $allowedTabs = ['general', 'contacts', 'addresses', 'orders', 'invoices'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        return View::make('admin.customer', [
            'customer' => $customer,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
        ]);
    }
}
