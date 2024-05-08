<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticlePrice;
use Illuminate\Support\Facades\DB;

class ArticlePriceService
{
    public function getPrice(string $articleNumber, int $customerID, string $currency)
    {
        $retailPrice = (float) DB::table('articles')
            ->select('retail_price_' . $currency)
            ->where('article_number', $articleNumber)
            ->pluck('retail_price_' . $currency)
            ->first();

        $articlePrice = ArticlePrice::where('article_number', $articleNumber)->first();

        $basePrice = (float) ($articlePrice->{'base_price_' . $currency} ?? 0);
        $basePrice = $basePrice ?: $retailPrice;

        $percent = (float) ($articlePrice->{'percent'} ?? 100);
        $percentInner = (float) ($articlePrice->{'percent_inner'} ?? 100);
        $percentMaster = (float) ($articlePrice->{'percent_master'} ?? 100);

        return [
            'default' => ($basePrice * ($percent / 100)),
            'inner' => ($basePrice * ($percentInner / 100)),
            'master' => ($basePrice * ($percentMaster / 100)),
        ];
    }

    public function getPriceList(int $customerID, string $currency, string $supplierNumber = '', string $sorting = '', string $articleNumber = '', string $eolStatus = '')
    {
        $articlesQuery = DB::table('articles')
            ->select(
                'article_number', 'description', 'stock', 'external_cost', 'cost_price_avg',
                ('retail_price_' . $currency . ' AS retail_price'),
                ('rek_price_' . $currency . ' AS rek_price')
            );

        switch ($sorting) {
            case 'latest':
                $articlesQuery->orderBy('created_at', 'DESC');
                break;

            case 'bestsellers':
                $articlesQuery->orderBy('sales_30_days', 'DESC');
                break;

            default:
                $articlesQuery->orderBy('article_number');
                break;
        }

        if ($articleNumber) {
            $articlesQuery->where('article_number', $articleNumber);
        }
        elseif ($supplierNumber) {
            $articlesQuery->where('supplier_number', $supplierNumber);
        }

        switch ($eolStatus) {
            case 'eol_stock':
                $articlesQuery->where('status', '!=', 'Active')
                    ->where('stock', '>', 0);
                break;

            case 'eol_no_stock':
                $articlesQuery->where('status', '!=', 'Active')
                    ->where('stock', '<=', 0);
                break;

            case 'active':
                $articlesQuery->where('status', '=', 'Active');
                break;
        }

        $articles = $articlesQuery->get();

        $priceList = [];

        foreach ($articles as $article) {
            $articlePrice = ArticlePrice::where('article_number', $article->article_number)
                ->where('customer_id', $customerID)
                ->first();

            $rekPrice = $article->rek_price * 0.8;

            $basePrice = (float) ($articlePrice->{'base_price_' . $currency} ?? 0);
            $basePrice = $basePrice ?: $article->retail_price;

            $percent = (float) ($articlePrice->{'percent'} ?? 100);
            $percentInner = (float) ($articlePrice->{'percent_inner'} ?? 100);
            $percentMaster = (float) ($articlePrice->{'percent_master'} ?? 100);

            $defaultPrice = ($basePrice * ($percent / 100)) * 0.8;
            $innerPrice = ($basePrice * ($percentInner / 100)) * 0.8;
            $masterPrice = ($basePrice * ($percentMaster / 100)) * 0.8;

            $inPrice = $article->cost_price_avg;
            if (!$inPrice) {
                $inPrice = $article->external_cost;
            }

            $margin = 0;
            if ($defaultPrice && $inPrice) {
                $margin = (($defaultPrice - $inPrice) / $defaultPrice) * 100;
            }

            $resellerMargin = 0;
            if ($defaultPrice && $rekPrice) {
                $resellerMargin = (($rekPrice - $defaultPrice) / $rekPrice) * 100;
            }

            $priceList[] = [
                'article_number' => $article->article_number,
                'article_name' => $article->description,
                'stock' => $article->stock,
                'in_price' => $inPrice,
                'default' => $defaultPrice,
                'inner' => $innerPrice,
                'master' => $masterPrice,
                'rek_price' => $rekPrice,
                'margin' => $inPrice ? round($margin, 2) : 0,
                'reseller_margin' => round($resellerMargin, 2),
            ];
        }

        return $priceList;
    }

    public function setPrice(string $articleNumber, int $customerID, float $percent, float $percentInner, float $percentMaster): void
    {
        $articlePrice = ArticlePrice::where('article_number', $articleNumber)
            ->where('customer_id', $customerID)
            ->first();

        $isDefault = true;
        if ($percent != 100 || $percentInner != 100 || $percentMaster != 100) {
            $isDefault = false;
        }

        if ($articlePrice) {
            if ($isDefault) {
                $articlePrice->delete();
            }
            else {
                $articlePrice->update([
                    'percent' => $percent,
                    'percent_inner' => $percentInner,
                    'percent_master' => $percentMaster,
                ]);
            }
        }
        elseif(!$isDefault) {
            ArticlePrice::create([
                'article_number' => $articleNumber,
                'customer_id' => $customerID,
                'percent' => $percent,
                'percent_inner' => $percentInner,
                'percent_master' => $percentMaster,
            ]);
        }
    }
}
