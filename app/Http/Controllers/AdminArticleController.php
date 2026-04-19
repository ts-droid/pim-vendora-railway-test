<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ApiKey;
use App\Models\BidVariant;
use App\Models\Brand;
use App\Models\BundleComponent;
use App\Models\SupplierArticlePrice;
use App\Services\GS1\Gs1ValidooService;
use App\Services\Pricing\CostResolver;
use App\Services\Pricing\PriceCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin article detail page for demo / Railway test.
 *
 * The real admin UI lives in the adm.vendora.se SPA (separate codebase).
 * This controller exists only to show how the Pricing calculator would look
 * when embedded as one of the tabs (General, Logistics, Web, Images, Files,
 * Reviews, Campaign, Google, RAW, FAQ, Outlet, Design for / use cases,
 * Pricing). Only General and Pricing have real content — the other tabs
 * render a "Mocked — lives in adm.vendora.se" placeholder.
 */
class AdminArticleController extends Controller
{
    public function __construct(
        private readonly PriceCalculatorService $calculator,
    ) {
    }

    /**
     * Create a brand-new Bundle article, using the current article as
     * its first component. Flow:
     *   1. Create new Article row (article_type='Bundle', empty EAN).
     *   2. Insert first BundleComponent pointing at the parent article.
     *   3. Article::saving hook auto-calls GS1 for a GTIN if configured
     *      and empty-EAN.
     *   4. Redirect to the new bundle's Pricing tab so user can add
     *      more components.
     */
    public function createBundleFromArticle(string $articleNumber, Request $request, Gs1ValidooService $gs1)
    {
        $parent = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $validated = $request->validate([
            'bundle_article_number' => 'required|string|max:255',
            'bundle_description' => 'nullable|string|max:500',
            'first_component_quantity' => 'nullable|integer|min:1',
        ]);
        $newNumber = trim($validated['bundle_article_number']);
        if (Article::where('article_number', $newNumber)->exists()) {
            return redirect('/admin/articles/' . rawurlencode($parent->article_number) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
                ->with('saved', "Artikelnummer '{$newNumber}' finns redan — välj ett unikt");
        }
        $description = $validated['bundle_description'] ?? ($parent->description . ' Bundle');
        $firstQty = (int) ($validated['first_component_quantity'] ?? 1);

        // Create the bundle article. category_ids is NOT NULL in schema.
        // Copy brand + supplier_number from parent so the new bundle has
        // coherent metadata for cascade + sourcing.
        $bundle = new Article();
        $bundle->article_number = $newNumber;
        $bundle->description = $description;
        $bundle->article_type = 'Bundle';
        $bundle->brand = $parent->brand;
        $bundle->supplier_number = $parent->supplier_number;
        $bundle->standard_reseller_margin = (float) ($parent->standard_reseller_margin ?? 0);
        $bundle->minimum_margin = (float) ($parent->minimum_margin ?? 0);
        $bundle->category_ids = '[]';
        $bundle->cost_price_avg = 0;
        $bundle->save();

        // First component row: parent × firstQty.
        BundleComponent::create([
            'bundle_article_number' => $bundle->article_number,
            'component_article_number' => $parent->article_number,
            'quantity' => $firstQty,
            'sort_order' => 1,
        ]);

        // GS1 generation — explicit (not relying on the saving-hook's
        // silent catch) so any Validoo error surfaces in the flash.
        $gtinNote = '';
        if (!$gs1->isConfigured()) {
            $gtinNote = 'GS1 ej konfigurerat — EAN lämnad tom';
        } elseif ($bundle->ean) {
            // Hook already ran successfully during save().
            $gtinNote = "GTIN: {$bundle->ean}";
        } else {
            try {
                $gtin = $gs1->generateAndActivate((string) ($description ?: 'Bundle'), null);
                $bundle->ean = $gtin;
                $bundle->save();
                $gtinNote = "GTIN: {$gtin}";
            } catch (\Throwable $e) {
                \Log::warning('GS1 GTIN generation failed on create-bundle', [
                    'bundle' => $bundle->article_number,
                    'error' => $e->getMessage(),
                ]);
                $gtinNote = 'GS1-fel: ' . $e->getMessage();
            }
        }

        $msg = "Bundle {$bundle->article_number} skapad med {$parent->article_number} × {$firstQty} som första komponent · {$gtinNote}";

        return redirect('/admin/articles/' . rawurlencode($bundle->article_number) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
            ->with('saved', $msg);
    }

    public function addBundleComponent(string $articleNumber, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $validated = $request->validate([
            'component_article_number' => 'required|string|max:255',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $componentNumber = trim($validated['component_article_number']);
        if ($componentNumber === $article->article_number) {
            return $this->redirectToPricing($article->article_number, $apiKey, 'En bundle kan inte innehålla sig själv');
        }
        if (!Article::where('article_number', $componentNumber)->exists()) {
            return $this->redirectToPricing($article->article_number, $apiKey, "Hittade inte artikel '{$componentNumber}' — kontrollera nummer");
        }

        // Promote to Bundle on first component, so the auto-GTIN hook +
        // downstream cost calc know this is a bundle.
        if ($article->article_type !== 'Bundle') {
            $article->article_type = 'Bundle';
            $article->save();
        }

        // Upsert: if this component already exists on the bundle,
        // bump its quantity rather than 500 on the UNIQUE constraint.
        $existing = BundleComponent::where('bundle_article_number', $article->article_number)
            ->where('component_article_number', $componentNumber)
            ->first();
        $addQty = (int) ($validated['quantity'] ?? 1);
        if ($existing) {
            $existing->quantity = (int) $existing->quantity + $addQty;
            $existing->save();
            return $this->redirectToPricing(
                $article->article_number,
                $apiKey,
                "Komponent {$componentNumber} finns redan — kvantitet ökad till {$existing->quantity}"
            );
        }

        $nextSort = 1 + (int) BundleComponent::where('bundle_article_number', $article->article_number)->max('sort_order');
        BundleComponent::create([
            'bundle_article_number' => $article->article_number,
            'component_article_number' => $componentNumber,
            'quantity' => $addQty,
            'sort_order' => $nextSort,
        ]);

        return $this->redirectToPricing($article->article_number, $apiKey, "Komponent {$componentNumber} tillagd");
    }

    public function updateBundleComponent(string $articleNumber, int $componentId, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $component = BundleComponent::where('id', $componentId)
            ->where('bundle_article_number', $article->article_number)
            ->first();
        abort_if(!$component, 404);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $component->quantity = (int) $validated['quantity'];
        if (array_key_exists('sort_order', $validated) && $validated['sort_order'] !== null) {
            $component->sort_order = (int) $validated['sort_order'];
        }
        $component->save();

        return $this->redirectToPricing($article->article_number, $apiKey, 'Komponent uppdaterad');
    }

    public function deleteBundleComponent(string $articleNumber, int $componentId, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        BundleComponent::where('id', $componentId)
            ->where('bundle_article_number', $article->article_number)
            ->delete();

        return $this->redirectToPricing($article->article_number, $apiKey, 'Komponent borttagen');
    }

    public function generateGTIN(string $articleNumber, Request $request, Gs1ValidooService $gs1)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        if (!$gs1->isConfigured()) {
            return $this->redirectToPricing(
                $article->article_number,
                $apiKey,
                'GS1 ej konfigurerat: sätt GS1_API_KEY + GS1_COMPANY_PREFIX i Railway-envet'
            );
        }

        try {
            $gtin = $gs1->generateAndActivate(
                (string) ($article->description ?: 'Bundle ' . $article->article_number),
                null
            );
            $article->ean = $gtin;
            $article->save();
            return $this->redirectToPricing($article->article_number, $apiKey, "GTIN genererat: {$gtin}");
        } catch (\Throwable $e) {
            return $this->redirectToPricing(
                $article->article_number,
                $apiKey,
                'GS1-fel: ' . $e->getMessage()
            );
        }
    }

    public function toggleBid(string $articleNumber, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $article->bid_enabled = (bool) $request->boolean('bid_enabled');
        $article->save();

        return $this->redirectToPricing($article->article_number, $apiKey, 'BID ' . ($article->bid_enabled ? 'aktiverat' : 'avaktiverat'));
    }

    public function addBidVariant(string $articleNumber, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $nextSort = 1 + (int) BidVariant::where('article_number', $article->article_number)->max('sort_order');
        BidVariant::create([
            'article_number' => $article->article_number,
            'variant_sku' => '',
            'cost' => 0,
            'fixed_price' => 0,
            'min_margin' => 0,
            'sort_order' => $nextSort,
        ]);

        return $this->redirectToPricing($article->article_number, $apiKey, 'Ny BID-variant tillagd');
    }

    public function updateBidVariant(string $articleNumber, int $variantId, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        $variant = BidVariant::where('id', $variantId)
            ->where('article_number', $article->article_number)
            ->first();
        abort_if(!$variant, 404, 'BID variant not found for this article');

        $validated = $request->validate([
            'variant_sku' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'fixed_price' => 'nullable|numeric|min:0',
            'min_margin' => 'nullable|numeric|min:0|max:100',
        ]);
        $variant->variant_sku = (string) ($validated['variant_sku'] ?? '');
        $variant->cost = (float) ($validated['cost'] ?? 0);
        $variant->fixed_price = (float) ($validated['fixed_price'] ?? 0);
        $variant->min_margin = (float) ($validated['min_margin'] ?? 0);
        $variant->save();

        return $this->redirectToPricing($article->article_number, $apiKey, 'Variant uppdaterad');
    }

    public function deleteBidVariant(string $articleNumber, int $variantId, Request $request)
    {
        $article = $this->requireArticle($articleNumber);
        $apiKey = $this->requireApiKey($request);

        BidVariant::where('id', $variantId)
            ->where('article_number', $article->article_number)
            ->delete();

        return $this->redirectToPricing($article->article_number, $apiKey, 'Variant borttagen');
    }

    private function requireArticle(string $articleNumber): Article
    {
        $article = Article::where('article_number', $articleNumber)->first();
        abort_if(!$article, 404);
        return $article;
    }

    private function requireApiKey(Request $request): string
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');
        return $apiKey;
    }

    private function redirectToPricing(string $articleNumber, string $apiKey, string $msg)
    {
        return redirect('/admin/articles/' . rawurlencode($articleNumber) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
            ->with('saved', $msg);
    }

    public function updatePricing(string $articleNumber, Request $request)
    {
        $article = Article::where('article_number', $articleNumber)->first();
        abort_if(!$article, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $validated = $request->validate([
            'standard_reseller_margin' => 'nullable|numeric|min:0|max:100',
            'minimum_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        // Empty input → 0 (article row columns are NOT NULL in the
        // schema). The cascade still works: article reads its own value
        // first, but the view shows the brand default side-by-side so
        // you can compare and tell whether the article is overriding.
        $article->standard_reseller_margin = (float) ($validated['standard_reseller_margin'] ?? 0);
        $article->minimum_margin = (float) ($validated['minimum_margin'] ?? 0);
        $article->save();

        return redirect('/admin/articles/' . rawurlencode($article->article_number) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
            ->with('saved', 'Sparade ' . $article->article_number);
    }

    public function show(string $articleNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $article = Article::where('article_number', $articleNumber)->first();
        abort_if(!$article, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $tab = $request->input('tab', 'general');
        // Tab order matches the adm.vendora.se admin screen, but with Pricing
        // inserted right after General (user's preferred placement).
        $allowedTabs = ['general', 'pricing', 'logistics', 'web', 'images', 'files',
            'reviews', 'campaign', 'google', 'raw', 'faq', 'outlet', 'design'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        $initial = $this->calculator->initialState($article);

        // Resolve brand-level defaults so the Pricing tab can show the
        // real cascade (article override → brand default → global).
        $brand = $article->brand
            ? Brand::where('name', $article->brand)->first()
            : null;

        // Current cost is the supplier's raw purchase price in *their*
        // currency (USD, EUR, SEK…). Lives in supplier_article_prices
        // keyed on article_number. external_cost on articles is the
        // SEK-converted fallback, not the source-of-truth any longer.
        $supplierPrice = SupplierArticlePrice::where('article_number', $article->article_number)
            ->orderByDesc('updated_at')
            ->first();

        // BID variants — loaded only on the pricing tab (the only place
        // they are rendered) to keep other tabs light.
        $bidVariants = $tab === 'pricing'
            ? BidVariant::where('article_number', $article->article_number)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        // Bundle components + computed bundle cost + available stock.
        // Cost uses CostResolver (falls back to supplier price converted
        // to SEK when cost_price_avg is 0). Stock is MIN of
        // component.stock_on_hand ÷ quantity_in_bundle.
        $bundleComponents = collect();
        $bundleCost = 0.0;
        $bundleStock = null;
        $componentCostBreakdowns = [];
        if ($tab === 'pricing') {
            $bundleComponents = BundleComponent::where('bundle_article_number', $article->article_number)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with('component:article_number,description,brand,cost_price_avg,external_cost,stock_on_hand,ean')
                ->get();

            if ($bundleComponents->isNotEmpty()) {
                $bundleStock = PHP_INT_MAX;
                foreach ($bundleComponents as $bc) {
                    $compArt = $bc->component;
                    if (!$compArt) {
                        $bundleStock = 0;
                        continue;
                    }
                    $breakdown = CostResolver::resolveBreakdown($compArt);
                    $componentCostBreakdowns[$bc->id] = $breakdown;
                    $bundleCost += $breakdown['sek'] * (int) $bc->quantity;

                    $qty = max(1, (int) $bc->quantity);
                    $compStock = (int) ($compArt->stock_on_hand ?? 0);
                    $bundleStock = min($bundleStock, intdiv($compStock, $qty));
                }
                if ($bundleStock === PHP_INT_MAX) {
                    $bundleStock = 0;
                }
            }
        }

        // Own-cost breakdown (non-bundle articles) — lets us show where
        // the SEK figure comes from on the Pricing tab.
        $ownCostBreakdown = $tab === 'pricing'
            ? CostResolver::resolveBreakdown($article)
            : null;

        $gs1Configured = app(Gs1ValidooService::class)->isConfigured();

        return View::make('admin.article', [
            'article' => $article,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'tabs' => $allowedTabs,
            'initial' => $initial,
            'brand' => $brand,
            'supplierPrice' => $supplierPrice,
            'bidVariants' => $bidVariants,
            'bundleComponents' => $bundleComponents,
            'bundleCost' => $bundleCost,
            'bundleStock' => $bundleStock,
            'componentCostBreakdowns' => $componentCostBreakdowns,
            'ownCostBreakdown' => $ownCostBreakdown,
            'gs1Configured' => $gs1Configured,
            'calcConfig' => [
                'articleNumber' => $article->article_number,
                'articleName' => $article->description,
                'apiKey' => $apiKey,
                'initial' => $initial,
            ],
        ]);
    }
}
