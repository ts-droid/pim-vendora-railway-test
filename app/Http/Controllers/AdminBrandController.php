<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Brand detail page — lets the user see and (mocked) edit the brand-level
 * default margins that cascade down to articles lacking their own override.
 */
class AdminBrandController extends Controller
{
    public function update(string $brandName, Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $brand = Brand::where('name', $brandName)->first();
        abort_if(!$brand, 404, 'Brand not found');

        $validated = $request->validate([
            'standard_reseller_margin' => 'nullable|numeric|min:0|max:100',
            'minimum_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        // Blank string → null (means "no brand-level default"), unlike 0
        // which means "cascade 0%". Keeps the cascade semantics intact.
        $brand->standard_reseller_margin = $validated['standard_reseller_margin'] === null || $validated['standard_reseller_margin'] === ''
            ? null : (float) $validated['standard_reseller_margin'];
        $brand->minimum_margin = $validated['minimum_margin'] === null || $validated['minimum_margin'] === ''
            ? null : (float) $validated['minimum_margin'];
        $brand->save();

        return redirect('/admin/brands/' . rawurlencode($brand->name) . '?api_key=' . urlencode($apiKey))
            ->with('saved', 'Sparade ' . $brand->name);
    }

    public function show(string $brandName, Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $brand = Brand::where('name', $brandName)->first();
        abort_if(!$brand, 404, 'Brand not found');

        $articleCount = Article::where('brand', $brand->name)->count();

        // Quick peek at the articles that will inherit from this brand.
        $articles = Article::where('brand', $brand->name)
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get(['article_number', 'description', 'standard_reseller_margin', 'minimum_margin']);

        // Suppliers som bär detta brand — kopplingen är en fri
        // sträng-match i suppliers.brand_name, samma värde som
        // articles.brand och brands.name.
        $suppliers = Supplier::where('brand_name', $brand->name)
            ->orderBy('name')
            ->get(['number', 'name', 'main_address_country as country', 'currency', 'type']);

        return View::make('admin.brand', [
            'apiKey' => $apiKey,
            'activeNav' => 'brands',
            'brand' => $brand,
            'articleCount' => $articleCount,
            'articles' => $articles,
            'suppliers' => $suppliers,
        ]);
    }

    private function requireApiKey(Request $request): string
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');
        return $apiKey;
    }
}
