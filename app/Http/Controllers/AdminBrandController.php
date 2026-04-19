<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\MarginRule;
use App\Models\Supplier;
use App\Services\Pricing\CostResolver;
use App\Services\Pricing\MarginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Brand detail page with three sub-tabs:
 *   - overview  (supplier card + brand default margins)
 *   - margins   (per-kategori marginalregler för detta brand)
 *   - articles  (alla artiklar i brandet + cost/RRP/marg/källa)
 */
class AdminBrandController extends Controller
{
    public function update(string $brandName, Request $request)
    {
        $apiKey = $this->requireApiKey($request);
        $brand = $this->requireBrand($brandName);

        $validated = $request->validate([
            'standard_reseller_margin' => 'nullable|numeric|min:0|max:100',
            'minimum_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        $brand->standard_reseller_margin = $validated['standard_reseller_margin'] === null || $validated['standard_reseller_margin'] === ''
            ? null : (float) $validated['standard_reseller_margin'];
        $brand->minimum_margin = $validated['minimum_margin'] === null || $validated['minimum_margin'] === ''
            ? null : (float) $validated['minimum_margin'];
        $brand->save();

        return $this->redirectToBrand($brand->name, $apiKey, 'overview', 'Sparade ' . $brand->name);
    }

    public function addRule(string $brandName, Request $request)
    {
        $apiKey = $this->requireApiKey($request);
        $brand = $this->requireBrand($brandName);

        $validated = $request->validate([
            'category_id' => 'nullable|integer',
        ]);
        $categoryId = $validated['category_id'] ?: null;

        // Don't allow duplicates on (brand, category).
        $existing = MarginRule::where('brand', $brand->name)
            ->where('category_id', $categoryId)
            ->first();
        if ($existing) {
            return $this->redirectToBrand($brand->name, $apiKey, 'margins', 'Regel för den kategorin finns redan');
        }

        MarginRule::create([
            'brand' => $brand->name,
            'category_id' => $categoryId,
            'reseller_margin' => null,
            'minimum_margin' => null,
        ]);

        return $this->redirectToBrand($brand->name, $apiKey, 'margins', 'Ny kategoriregel tillagd');
    }

    public function updateRule(string $brandName, int $ruleId, Request $request)
    {
        $apiKey = $this->requireApiKey($request);
        $brand = $this->requireBrand($brandName);

        $rule = MarginRule::where('id', $ruleId)->where('brand', $brand->name)->first();
        abort_if(!$rule, 404);

        $validated = $request->validate([
            'reseller_margin' => 'nullable|numeric|min:0|max:100',
            'minimum_margin' => 'nullable|numeric|min:0|max:100',
        ]);
        $rule->reseller_margin = $this->emptyToNull($validated['reseller_margin'] ?? null);
        $rule->minimum_margin = $this->emptyToNull($validated['minimum_margin'] ?? null);
        $rule->save();

        return $this->redirectToBrand($brand->name, $apiKey, 'margins', 'Regel uppdaterad');
    }

    public function deleteRule(string $brandName, int $ruleId, Request $request)
    {
        $apiKey = $this->requireApiKey($request);
        $brand = $this->requireBrand($brandName);

        MarginRule::where('id', $ruleId)->where('brand', $brand->name)->delete();

        return $this->redirectToBrand($brand->name, $apiKey, 'margins', 'Regel borttagen');
    }

    public function show(string $brandName, Request $request)
    {
        $apiKey = $this->requireApiKey($request);
        $brand = $this->requireBrand($brandName);

        $tab = $request->input('tab', 'overview');
        $allowedTabs = ['overview', 'margins', 'articles'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $articleCount = Article::where('brand', $brand->name)->count();
        $suppliers = Supplier::where('brand_name', $brand->name)
            ->orderBy('name')
            ->get(['number', 'name', 'main_address_country as country', 'currency', 'type']);

        $data = [
            'apiKey' => $apiKey,
            'activeNav' => 'brands',
            'brand' => $brand,
            'articleCount' => $articleCount,
            'suppliers' => $suppliers,
            'activeTab' => $tab,
        ];

        if ($tab === 'margins') {
            $data += $this->marginsTabData($brand);
        } elseif ($tab === 'articles') {
            $data += $this->articlesTabData($brand);
        }

        return View::make('admin.brand', $data);
    }

    private function marginsTabData(Brand $brand): array
    {
        // Rules already defined for this brand
        $rules = MarginRule::where('brand', $brand->name)
            ->with('category:id,title_sv,parent_id')
            ->orderByRaw('category_id IS NULL DESC')
            ->get();

        // Category IDs + article counts for this brand. articles.category_ids
        // is json-ish; expand in PHP. Using the query builder (not Eloquent)
        // so the Article model's retrieved-hook doesn't fire on partial
        // rows — it needs article_number to drive downstream services.
        $categoryArticleCounts = [];
        DB::table('articles')
            ->where('brand', $brand->name)
            ->whereNotNull('category_ids')
            ->select('category_ids')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$categoryArticleCounts) {
                foreach ($rows as $r) {
                    $ids = $this->decodeCategoryIds($r->category_ids);
                    foreach ($ids as $id) {
                        $categoryArticleCounts[$id] = ($categoryArticleCounts[$id] ?? 0) + 1;
                    }
                }
            });

        $brandCategoryIds = array_keys($categoryArticleCounts);
        $brandCategories = ArticleCategory::whereIn('id', $brandCategoryIds)
            ->orderBy('title_sv')
            ->get(['id', 'title_sv']);

        // Kategorier som artiklar ligger i men som saknar en egen
        // (brand, category)-regel — de ärver varumärkets standard.
        $rulesCategoryIds = $rules->pluck('category_id')->filter()->all();
        $uncoveredCategories = $brandCategories
            ->whereNotIn('id', $rulesCategoryIds)
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title_sv, 'count' => $categoryArticleCounts[$c->id] ?? 0])
            ->sortByDesc('count')
            ->values();

        // För lägg-till-dropdownen
        $allCategoriesForPicker = ArticleCategory::orderBy('title_sv')->get(['id', 'title_sv']);

        return [
            'rules' => $rules,
            'uncoveredCategories' => $uncoveredCategories,
            'allCategoriesForPicker' => $allCategoriesForPicker,
            'brandTotalArticles' => array_sum($categoryArticleCounts),
        ];
    }

    private function articlesTabData(Brand $brand): array
    {
        $q = request()->string('q')->toString();

        $articles = Article::where('brand', $brand->name)
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('article_number', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            }))
            ->orderBy('article_number')
            ->limit(300)
            ->get(['article_number', 'description', 'brand', 'supplier_number', 'category_ids',
                   'cost_price_avg', 'external_cost', 'standard_reseller_margin', 'minimum_margin',
                   'article_type', 'rek_price_SEK']);

        $rows = $articles->map(function (Article $a) {
            $cost = CostResolver::resolveBreakdown($a);
            $margin = MarginResolver::resolveBreakdown($a);
            return [
                'article_number' => $a->article_number,
                'description' => $a->description,
                'cost_sek' => $cost['sek'],
                'cost_source' => $cost['source'],
                'rek_price_SEK' => (float) ($a->rek_price_SEK ?? 0),
                'reseller_margin' => $margin['reseller']['margin'],
                'reseller_source' => $margin['reseller']['source'],
                'category_id' => $this->primaryCategoryId($a),
            ];
        });

        $categoryIds = $rows->pluck('category_id')->filter()->unique()->values();
        $categories = ArticleCategory::whereIn('id', $categoryIds)->pluck('title_sv', 'id');

        return [
            'articleRows' => $rows,
            'articleCategories' => $categories,
            'articleSearch' => $q,
        ];
    }

    private function primaryCategoryId(Article $article): ?int
    {
        $ids = $this->decodeCategoryIds($article->category_ids);
        return $ids[0] ?? null;
    }

    private function decodeCategoryIds($raw): array
    {
        if ($raw === null || $raw === '' || $raw === '[]') {
            return [];
        }
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private function requireApiKey(Request $request): string
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');
        return $apiKey;
    }

    private function requireBrand(string $brandName): Brand
    {
        $brand = Brand::where('name', $brandName)->first();
        abort_if(!$brand, 404, 'Brand not found');
        return $brand;
    }

    private function redirectToBrand(string $brandName, string $apiKey, string $tab, string $msg)
    {
        $url = '/admin/brands/' . rawurlencode($brandName)
            . '?api_key=' . urlencode($apiKey)
            . '&tab=' . $tab;
        return redirect($url)->with('saved', $msg);
    }

    private function emptyToNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }
}
