<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Brand detail page — lets the user see and (mocked) edit the brand-level
 * default margins that cascade down to articles lacking their own override.
 */
class AdminBrandController extends Controller
{
    public function show(string $brandName, Request $request)
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');

        $brand = Brand::where('name', $brandName)->first();
        abort_if(!$brand, 404, 'Brand not found');

        $articleCount = Article::where('brand', $brand->name)->count();

        // Quick peek at the articles that will inherit from this brand.
        $articles = Article::where('brand', $brand->name)
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get(['article_number', 'description', 'standard_reseller_margin', 'minimum_margin']);

        return View::make('admin.brand', [
            'apiKey' => $apiKey,
            'activeNav' => 'brands',
            'brand' => $brand,
            'articleCount' => $articleCount,
            'articles' => $articles,
        ]);
    }
}
