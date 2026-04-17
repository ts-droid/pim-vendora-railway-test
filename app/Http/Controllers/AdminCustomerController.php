<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ArticlePrice;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Aggregate sales stats so the CRM tab can pass them along to the
        // Vendora CRM. Computed only on the CRM tab to keep other tabs fast.
        $crmStats = null;
        if ($tab === 'crm') {
            $crmStats = $this->customerSalesStats($customer->customer_number);
        }

        // Build CRM URL by replacing {vat} in the template. If vat_number is
        // empty we still render the tab but mark the link as unavailable.
        // Aggregated stats are appended as query params so the CRM can
        // render them directly (revenue, top brands, order count).
        $crmTemplate = (string) config('services.vendora_crm.customer_url_template');
        $crmUrl = null;
        if ($customer->vat_number) {
            $crmUrl = str_replace('{vat}', rawurlencode($customer->vat_number), $crmTemplate);
            if ($crmStats) {
                $crmUrl .= (str_contains($crmUrl, '?') ? '&' : '?')
                    . http_build_query([
                        'customer_number' => $customer->customer_number,
                        'customer_name' => $customer->name,
                        'revenue_12m_sek' => $crmStats['revenue_12m_sek'],
                        'revenue_30d_sek' => $crmStats['revenue_30d_sek'],
                        'orders_12m' => $crmStats['orders_12m'],
                        'top_brands' => implode(',', array_map(
                            fn ($b) => $b['brand'] . ':' . $b['revenue_sek'],
                            $crmStats['top_brands']
                        )),
                    ]);
            }
        }
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
            'crmStats' => $crmStats,
            'pricelist' => $pricelist,
        ]);
    }

    /**
     * 12-month and 30-day revenue + top brands for a customer. Joined
     * across sales_order_lines → sales_orders → articles, summed in SEK
     * (quantity × unit_price). Returns null-safe numeric scalars plus
     * up to 5 brand breakdowns.
     */
    private function customerSalesStats(string $customerNumber): array
    {
        $rev12m = (float) DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
            ->where('o.customer', $customerNumber)
            ->where('l.created_at', '>=', now()->subMonths(12))
            ->sum(DB::raw('l.quantity * l.unit_price'));

        $rev30d = (float) DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
            ->where('o.customer', $customerNumber)
            ->where('l.created_at', '>=', now()->subDays(30))
            ->sum(DB::raw('l.quantity * l.unit_price'));

        $orders12m = (int) DB::table('sales_orders')
            ->where('customer', $customerNumber)
            ->where('date', '>=', now()->subMonths(12)->format('Y-m-d'))
            ->count();

        $topBrands = DB::table('sales_order_lines as l')
            ->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')
            ->join('articles as a', 'a.article_number', '=', 'l.article_number')
            ->where('o.customer', $customerNumber)
            ->where('l.created_at', '>=', now()->subMonths(12))
            ->whereNotNull('a.brand')
            ->where('a.brand', '!=', '')
            ->selectRaw('a.brand, ROUND(SUM(l.quantity * l.unit_price), 0) as revenue_sek')
            ->groupBy('a.brand')
            ->orderByDesc('revenue_sek')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['brand' => $r->brand, 'revenue_sek' => (int) $r->revenue_sek])
            ->toArray();

        return [
            'revenue_12m_sek' => (int) round($rev12m),
            'revenue_30d_sek' => (int) round($rev30d),
            'orders_12m' => $orders12m,
            'top_brands' => $topBrands,
        ];
    }
}
