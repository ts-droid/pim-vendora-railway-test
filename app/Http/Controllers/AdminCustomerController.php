<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ArticlePrice;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin customer detail page.
 *
 * Vendora does not have a real customer-card UI today — this view is the
 * starting point for the unified one described by the user:
 *
 *   General | Contacts | Prislista | Web-inloggningar | CRM
 *
 * The CRM tab embeds Vendora CRM (separate service) in an iframe,
 * matched on vat_number. Configure via VENDORA_CRM_CUSTOMER_URL env.
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
        $allowedTabs = ['general', 'contacts', 'pricelist', 'logins', 'crm'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        // Build CRM URL by replacing {vat} in the template. If vat_number is
        // empty we still render the tab but mark the link as unavailable.
        $crmTemplate = (string) config('services.vendora_crm.customer_url_template');
        $crmUrl = $customer->vat_number
            ? str_replace('{vat}', urlencode($customer->vat_number), $crmTemplate)
            : null;
        $crmIframe = (bool) config('services.vendora_crm.embed_in_iframe');

        // Pricelist — optional per-customer article prices. Empty in the
        // imported Railway dataset; we render "inga kundspecifika priser"
        // when none exist.
        $pricelist = ($tab === 'pricelist')
            ? ArticlePrice::where('customer_id', $customer->customer_number)
                ->orderBy('article_number')
                ->limit(200)
                ->get()
            : collect();

        return View::make('admin.customer', [
            'customer' => $customer,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'crmUrl' => $crmUrl,
            'crmIframe' => $crmIframe,
            'pricelist' => $pricelist,
        ]);
    }
}
