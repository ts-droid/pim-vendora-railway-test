<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\ArticlePrice;
use App\Models\ArticleSupport;
use App\Models\BidVariant;
use App\Models\Customer;
use App\Models\CustomerBidAccess;
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

        // Pricelist-tabben samlar all specialprissättning som rör kunden:
        //   1. Kundspecifika priser (article_prices)
        //   2. BID-varianter tillgängliga (bid_variants på artiklar med
        //      bid_enabled=true) — representerar offert-priser som kan
        //      erbjudas kunden
        //   3. Kundrabatter per varumärke (article_supports layer=customer
        //      grupperade per brand) — visar vad som redan ligger i
        //      prislistor som rabatt "Till kund"
        $pricelist = collect();
        $bidVariantsAvailable = collect();
        $bidAccessRows = collect();
        $customerDiscountsByBrand = collect();

        if ($tab === 'pricelist') {
            $pricelist = ArticlePrice::where('customer_id', $customer->customer_number)
                ->orderBy('article_number')
                ->limit(200)
                ->get();

            // BID-varianter filtrerade på kundens access-whitelist.
            // Utan access-rad → kunden ser inga BID för artikeln
            // oavsett articles.bid_enabled.
            $accessArticleNumbers = CustomerBidAccess::where('customer_number', $customer->customer_number)
                ->pluck('article_number');

            $bidVariantsAvailable = $accessArticleNumbers->isEmpty()
                ? collect()
                : BidVariant::whereIn('article_number', $accessArticleNumbers)
                    ->whereIn('article_number', DB::table('articles')->where('bid_enabled', 1)->pluck('article_number'))
                    ->with('article:article_number,description,brand,supplier_number')
                    ->orderBy('article_number')
                    ->orderBy('sort_order')
                    ->limit(200)
                    ->get();

            // Full access-lista (även utan bid_enabled) så användaren
            // kan revoke även gamla rader.
            $bidAccessRows = CustomerBidAccess::where('customer_number', $customer->customer_number)
                ->orderBy('article_number')
                ->get()
                ->map(function ($row) {
                    $a = DB::table('articles')->where('article_number', $row->article_number)->first(['description', 'brand', 'bid_enabled']);
                    return [
                        'id' => $row->id,
                        'article_number' => $row->article_number,
                        'description' => $a?->description ?? '—',
                        'brand' => $a?->brand ?? '',
                        'bid_enabled' => (bool) ($a?->bid_enabled ?? false),
                    ];
                });

            // Kundrabatter per brand: article_supports där layer='customer'
            // grupperat på artikelns brand. Join via DB::table för att
            // undvika Eloquent retrieved-hook på partiella articles-rader.
            $customerSupportRows = DB::table('article_supports as s')
                ->join('articles as a', 'a.article_number', '=', 's.article_number')
                ->whereIn('s.layer', ['customer'])
                ->whereNotNull('a.brand')
                ->where('a.brand', '!=', '')
                ->select(
                    'a.brand',
                    's.article_number',
                    'a.description',
                    's.customer_type',
                    's.value',
                    's.is_percentage',
                    's.currency',
                    's.date_from',
                    's.date_to'
                )
                ->orderBy('a.brand')
                ->orderBy('s.article_number')
                ->get();

            $customerDiscountsByBrand = $customerSupportRows->groupBy('brand');
        }

        return View::make('admin.customer', [
            'customer' => $customer,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'crmUrl' => $crmUrl,
            'crmIframe' => $crmIframe,
            'crmStats' => $crmStats,
            'pricelist' => $pricelist,
            'bidVariantsAvailable' => $bidVariantsAvailable,
            'bidAccessRows' => $bidAccessRows,
            'customerDiscountsByBrand' => $customerDiscountsByBrand,
        ]);
    }

    public function grantBidAccess(string $customerNumber, Request $request)
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $customer = Customer::where('customer_number', $customerNumber)->first();
        abort_if(!$customer, 404);

        $validated = $request->validate([
            'article_number' => 'required|string|max:255',
        ]);
        $articleNumber = trim($validated['article_number']);

        $backPath = '/admin/customers/' . rawurlencode($customer->customer_number) . '?api_key=' . urlencode($apiKey) . '&tab=pricelist';

        if (!Article::where('article_number', $articleNumber)->exists()) {
            return redirect($backPath)->with('saved', "Hittade inte artikel '{$articleNumber}'");
        }

        // Idempotent upsert — unique-index säkrar mot dubletter men
        // firstOrCreate ger snyggare flash-text.
        $existed = CustomerBidAccess::where('customer_number', $customer->customer_number)
            ->where('article_number', $articleNumber)
            ->exists();
        if ($existed) {
            return redirect($backPath)->with('saved', "BID-access till {$articleNumber} fanns redan");
        }

        CustomerBidAccess::create([
            'customer_number' => $customer->customer_number,
            'article_number' => $articleNumber,
        ]);

        return redirect($backPath)->with('saved', "BID-access till {$articleNumber} beviljad");
    }

    public function revokeBidAccess(string $customerNumber, int $accessId, Request $request)
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $customer = Customer::where('customer_number', $customerNumber)->first();
        abort_if(!$customer, 404);

        $access = CustomerBidAccess::where('id', $accessId)
            ->where('customer_number', $customer->customer_number)
            ->first();
        abort_if(!$access, 404);

        $articleNumber = $access->article_number;
        $access->delete();

        return redirect('/admin/customers/' . rawurlencode($customer->customer_number) . '?api_key=' . urlencode($apiKey) . '&tab=pricelist')
            ->with('saved', "BID-access till {$articleNumber} borttagen");
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
