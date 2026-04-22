<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\MarginRule;
use App\Models\Supplier;
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
        ]);

        // Parse-steget kommer i nästa iteration. För nu — spara filen
        // temporärt och visa rad-räkning så användaren ser att det kom
        // fram. Kolumnmappning + faktisk artikeluppdatering/-skapande
        // byggs ut när vi kommer överens om förväntade kolumner.
        $file = $request->file('price_file');
        $rows = 0;
        if ($handle = fopen($file->getRealPath(), 'r')) {
            while (fgetcsv($handle, 0, ';') !== false) {
                $rows++;
            }
            fclose($handle);
        }

        return redirect('/admin/suppliers/' . rawurlencode($supplier->number) . '?api_key=' . urlencode($apiKey) . '&tab=pricing')
            ->with('saved', "Prisfil mottagen: {$file->getClientOriginalName()} ({$rows} rader). Parsning + matchning mot artikelregistret byggs ut i nästa steg.");
    }
}
