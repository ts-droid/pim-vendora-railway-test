<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/**
 * Global admin-nav. Matches the Prislistor SPA's top-bar pattern:
 * Artiklar · Leverantörer · Kunder · Varumärken.
 *
 * Each tab loads its own list. Detail-pages (article/supplier/customer)
 * live in the other three controllers — this one handles just the lists
 * and the landing redirect.
 */
class AdminIndexController extends Controller
{
    public function redirectToDefault(Request $request)
    {
        $apiKey = (string) $request->input('api_key', '');
        return redirect('/admin/articles?api_key=' . urlencode($apiKey));
    }

    public function articles(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $q = trim((string) $request->input('q', ''));
        $query = Article::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('article_number', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%")
                  ->orWhere('brand', 'like', "%{$q}%")
                  ->orWhere('ean', 'like', "%{$q}%");
            });
        }
        $articles = $query->orderByDesc('updated_at')
            ->limit(50)
            ->get(['article_number', 'description', 'brand', 'supplier_number', 'article_type', 'updated_at']);

        return View::make('admin.list-articles', [
            'apiKey' => $apiKey,
            'activeNav' => 'articles',
            'articles' => $articles,
            'q' => $q,
            'totalCount' => Article::count(),
        ]);
    }

    public function suppliers(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $q = trim((string) $request->input('q', ''));
        $query = Supplier::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('number', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('org_number', 'like', "%{$q}%");
            });
        }
        $suppliers = $query->orderBy('name')
            ->limit(50)
            ->get(['number', 'name', 'main_address_country as country', 'org_number']);

        return View::make('admin.list-suppliers', [
            'apiKey' => $apiKey,
            'activeNav' => 'suppliers',
            'suppliers' => $suppliers,
            'q' => $q,
            'totalCount' => Supplier::count(),
        ]);
    }

    public function customers(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $q = trim((string) $request->input('q', ''));
        $query = Customer::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('customer_number', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('org_number', 'like', "%{$q}%")
                  ->orWhere('vat_number', 'like', "%{$q}%");
            });
        }
        $customers = $query->orderByDesc('sales_last_30_days')
            ->limit(50)
            ->get(['customer_number', 'name', 'country', 'sales_last_30_days', 'vat_number']);

        return View::make('admin.list-customers', [
            'apiKey' => $apiKey,
            'activeNav' => 'customers',
            'customers' => $customers,
            'q' => $q,
            'totalCount' => Customer::count(),
        ]);
    }

    public function brands(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $q = trim((string) $request->input('q', ''));
        // Use the query builder (not Eloquent) so the Article model's
        // `retrieved` hook doesn't fire on partial rows — it expects
        // article_number to be set and blows up on aggregate rows.
        $query = DB::table('articles')
            ->selectRaw('brand, COUNT(*) as article_count')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->groupBy('brand');
        if ($q !== '') {
            $query->having('brand', 'like', "%{$q}%");
        }
        $brands = $query->orderByDesc('article_count')
            ->limit(100)
            ->get();

        return View::make('admin.list-brands', [
            'apiKey' => $apiKey,
            'activeNav' => 'brands',
            'brands' => $brands,
            'q' => $q,
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
