<?php

namespace App\Utilities;

use App\Services\BrandPageService;

class BrandPageUtility
{
    public static function getBrandingData(string $domain): array
    {
        $__utilityLogContext = [
            'utility' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked utility static method.', $__utilityLogContext);

        $endpoint = 'https://' . $domain . '/api/v1/pages/site/get-by-domain';

        $brandPageService = new BrandPageService();
        $response = $brandPageService->callAPI('GET', $endpoint, [
            'domain' => $domain
        ]);

        if ($response['success']) {
            $domain = $response['data']['domain'] ?? '';
            $brandName = $response['data']['name'] ?? '';
            $logoPath = $response['data']['logo']['path'] ?? '';
            $logoMultiplier = $response['data']['logo_multiplier'] ?? 1;

            if ($domain && $brandName && $logoPath) {
                return [
                    'is_brand' => true,
                    'brand_name' => $brandName,
                    'domain' => $domain,
                    'site_url' => 'https://' . $domain,
                    'logo_url' => 'https://' . $domain . '/storage/' . $logoPath,
                    'logo_path' => null,
                    'logo_multiplier' => $logoMultiplier,
                    'customer_review_url' => 'https://' . $domain . '/{lang}/customer-review?sku={sku}&rating={rating}',
                ];
            }
        }

        return [
            'is_brand' => false,
            'brand_name' => 'Vendora Nordic AB',
            'domain' => 'vendora.se',
            'site_url' => 'https://www.vendora.se',
            'logo_url' => asset('/assets/img/logos/logo_vendora.png'),
            'logo_path' => public_path('/assets/img/logos/logo_vendora.png'),
            'logo_multiplier' => 1,
            'customer_review_url' => route('customer.review', ['article_id' => '{article_id}', 'lang' => '{lang}', 'rating' => '{rating}']),
        ];
    }
}
