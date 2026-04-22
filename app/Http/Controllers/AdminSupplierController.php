<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\MarginRule;
use App\Models\Supplier;
use App\Models\SupplierArticlePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Mock of the Vendora admin supplier detail page. Tabbar:
 *   general · contacts · logistics · purchasing · pricing
 *
 * Pricing-fliken visar alla margin_rules som påverkar artiklar från
 * leverantörens varumärke, plus en prisfil-uppladdningsruta för att
 * koppla/uppdatera artiklar mot vårt artikelregister. Full filparsing
 * är en senare iteration — UI + endpoint-stub finns nu.
 */
class AdminSupplierController extends Controller
{
    public function show(string $supplierNumber, Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $supplier = Supplier::where('number', $supplierNumber)->first();
        abort_if(!$supplier, 404);

        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $tab = $request->input('tab', 'general');
        $allowedTabs = ['general', 'contacts', 'logistics', 'purchasing', 'pricing'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'general';
        }

        // Link supplier → brand via suppliers.brand_name ↔ brands.name.
        $brand = $supplier->brand_name
            ? Brand::where('name', $supplier->brand_name)->first()
            : null;

        $data = [
            'supplier' => $supplier,
            'apiKey' => $apiKey,
            'activeTab' => $tab,
            'brand' => $brand,
        ];

        if ($tab === 'pricing') {
            $data += $this->pricingTabData($supplier, $brand);
        }

        return View::make('admin.supplier', $data);
    }

    private function pricingTabData(Supplier $supplier, ?Brand $brand): array
    {
        if (!$brand) {
            return [
                'pricingRules' => collect(),
                'pricingArticleCount' => 0,
                'pricingCategories' => collect(),
            ];
        }

        $rules = MarginRule::where('brand', $brand->name)
            ->with('category:id,title_sv,parent_id')
            ->orderByRaw('category_id IS NULL DESC')
            ->get();

        $articleCount = DB::table('articles')
            ->where('brand', $brand->name)
            ->count();

        // Kategorier utan egen regel (deduced from categories found on
        // this brand's articles) — för picker-formen i UI:n.
        $categoryIdsUsed = DB::table('articles')
            ->where('brand', $brand->name)
            ->whereNotNull('category_ids')
            ->pluck('category_ids')
            ->flatMap(function ($raw) {
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                return is_array($decoded) ? array_map('intval', $decoded) : [];
            })
            ->unique()
            ->filter()
            ->values();

        $categories = ArticleCategory::whereIn('id', $categoryIdsUsed)
            ->orderBy('title_sv')
            ->get(['id', 'title_sv']);

        return [
            'pricingRules' => $rules,
            'pricingArticleCount' => $articleCount,
            'pricingCategories' => $categories,
        ];
    }

    public function uploadPriceFile(string $supplierNumber, Request $request)
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $supplier = Supplier::where('number', $supplierNumber)->first();
        abort_if(!$supplier, 404);

        $request->validate([
            'price_file' => 'required|file|mimes:csv,txt|max:10240',
            'apply' => 'nullable|boolean',
        ]);

        $file = $request->file('price_file');
        $apply = (bool) $request->boolean('apply');

        $parse = $this->parsePriceFile($file->getRealPath(), $supplier);
        $result = [
            'total_rows' => $parse['total_rows'],
            'matched_updates' => count($parse['matches']),
            'new_articles' => count($parse['creates']),
            'skipped' => count($parse['skipped']),
            'applied' => 0,
            'created' => 0,
        ];

        if ($apply) {
            $currency = $supplier->currency ?: 'SEK';
            foreach ($parse['matches'] as $row) {
                // external_cost lagras i SEK; lämna cost_price_avg orörd
                // (det är inventeringsberäknat snitt). För raw supplier-
                // pris i deras valuta går vi genom supplier_article_prices.
                SupplierArticlePrice::updateOrCreate(
                    ['article_number' => $row['article_number']],
                    ['price' => $row['cost'], 'currency' => $row['currency'] ?: $currency]
                );
                $result['applied']++;
            }
            foreach ($parse['creates'] as $row) {
                try {
                    Article::create([
                        'article_number' => $row['article_number'],
                        'manufacturer_article_number' => $row['manufacturer_article_number'] ?: $row['article_number'],
                        'description' => $row['description'] ?: $row['article_number'],
                        'ean' => $row['ean'] ?: null,
                        'supplier_number' => $supplier->number,
                        'brand' => $supplier->brand_name ?: '',
                        'article_type' => 'Stock Item',
                        'category_ids' => '[]',
                        'cost_price_avg' => 0,
                        'external_cost' => 0,
                    ]);
                    SupplierArticlePrice::updateOrCreate(
                        ['article_number' => $row['article_number']],
                        ['price' => $row['cost'], 'currency' => $row['currency'] ?: $currency]
                    );
                    $result['created']++;
                } catch (\Throwable $e) {
                    \Log::warning('Price-file article create failed', [
                        'article' => $row['article_number'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $msg = $apply
            ? "Tillämpat: {$result['applied']} pris-uppdateringar, {$result['created']} nya artiklar skapade"
            : "Förhandsvisning: {$result['matched_updates']} matchade, {$result['new_articles']} nya, {$result['skipped']} skippade · välj Tillämpa för att skriva";

        return redirect('/admin/suppliers/' . rawurlencode($supplier->number) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
            ->with('saved', $msg)
            ->with('priceFileResult', $result);
    }

    /**
     * Läs CSV (semikolon) och mappa rader mot articles-tabellen.
     *
     * Förväntade kolumner (header-raden):
     *   article_number;manufacturer_article_number;description;cost;currency;ean
     *
     * Alla kolumner utom article_number + cost är valfria. Matchning:
     *   1. Exakt article_number
     *   2. Fallback: manufacturer_article_number
     *
     * @return array{total_rows:int, matches:array, creates:array, skipped:array}
     */
    private function parsePriceFile(string $path, Supplier $supplier): array
    {
        $matches = $creates = $skipped = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['total_rows' => 0, 'matches' => [], 'creates' => [], 'skipped' => []];
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            return ['total_rows' => 0, 'matches' => [], 'creates' => [], 'skipped' => []];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $col = array_flip($header);

        $total = 0;
        while (($cols = fgetcsv($handle, 0, ';')) !== false) {
            $total++;
            $get = fn (string $name) => isset($col[$name]) ? trim((string) ($cols[$col[$name]] ?? '')) : '';
            $artNum = $get('article_number');
            $costStr = str_replace([' ', ','], ['', '.'], $get('cost'));
            $cost = $costStr === '' ? null : (float) $costStr;

            if ($artNum === '' || $cost === null) {
                $skipped[] = ['reason' => 'saknar article_number eller cost', 'row' => $artNum];
                continue;
            }

            $row = [
                'article_number' => $artNum,
                'manufacturer_article_number' => $get('manufacturer_article_number'),
                'description' => $get('description'),
                'cost' => $cost,
                'currency' => strtoupper($get('currency')) ?: ($supplier->currency ?: 'SEK'),
                'ean' => $get('ean'),
            ];

            // Match mot befintlig artikel. Första försök: artnr-exakt.
            $exists = DB::table('articles')->where('article_number', $artNum)->exists();
            if (!$exists && $row['manufacturer_article_number'] !== '') {
                $exists = DB::table('articles')
                    ->where('manufacturer_article_number', $row['manufacturer_article_number'])
                    ->exists();
            }

            if ($exists) {
                $matches[] = $row;
            } else {
                $creates[] = $row;
            }
        }
        fclose($handle);

        return [
            'total_rows' => $total,
            'matches' => $matches,
            'creates' => $creates,
            'skipped' => $skipped,
        ];
    }
}
