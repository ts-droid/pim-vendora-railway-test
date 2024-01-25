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

        $percent = (float) ($articlePrice->{'percent'} ?? 0);
        $percentInner = (float) ($articlePrice->{'percent_inner'} ?? 0);
        $percentMaster = (float) ($articlePrice->{'percent_master'} ?? 0);

        return [
            'default' => ($basePrice * ($percent / 100)),
            'inner' => ($basePrice * ($percentInner / 100)),
            'master' => ($basePrice * ($percentMaster / 100)),
        ];
    }

    public function getPriceList(int $customerID, string $currency, string $supplierNumber = '')
    {
        $articlesQuery = DB::table('articles')
            ->select('article_number', 'description', ('retail_price_' . $currency . ' AS retail_price'))
            ->orderBy('article_number');

        if ($supplierNumber) {
            $articlesQuery->where('supplier_number', $supplierNumber);
        }

        $articles = $articlesQuery->get();

        $priceList = [];

        foreach ($articles as $article) {
            $articlePrice = ArticlePrice::where('article_number', $article->article_number)->first();

            $basePrice = (float) ($articlePrice->{'base_price_' . $currency} ?? 0);
            $basePrice = $basePrice ?: $article->retail_price;

            $percent = (float) ($articlePrice->{'percent'} ?? 0);
            $percentInner = (float) ($articlePrice->{'percent_inner'} ?? 0);
            $percentMaster = (float) ($articlePrice->{'percent_master'} ?? 0);

            $priceList[] = [
                'article_number' => $article->article_number,
                'article_name' => $article->description,
                'default' => ($basePrice * ($percent / 100)),
                'inner' => ($basePrice * ($percentInner / 100)),
                'master' => ($basePrice * ($percentMaster / 100)),
            ];
        }

        return $priceList;
    }

    public function setPrice(string $articleNumber, int $customerID, float $percent, float $percentInner, float $percentMaster): void
    {
        $articlePrice = ArticlePrice::where('article_number', $articleNumber)
            ->where('customer_id', $customerID)
            ->first();

        if ($articlePrice) {
            $articlePrice->update([
                'percent' => $percent,
                'percent_inner' => $percentInner,
                'percent_master' => $percentMaster,
            ]);
        }
        else {
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
