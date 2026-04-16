<?php

namespace App\Services\Pricing;

use App\Models\Article;

/**
 * Resolves the effective reseller margin and minimum distributor margin
 * for an article. Falls through a hierarchy:
 *
 *   article.standard_reseller_margin  (article-specific override)
 *     → default constants           (sane fallback)
 *
 * In the future this can be extended with brand-level and category-level
 * margin rules (both exist in the separate Node.js system). This PR keeps
 * it intentionally small — the caller passes an Article and gets back a
 * struct with the effective margins and the source (for display).
 */
class MarginResolver
{
    public const DEFAULT_RESELLER_MARGIN = 25.0;
    public const DEFAULT_MINIMUM_MARGIN = 18.0;

    /**
     * @return array{
     *     reseller_margin: float,
     *     minimum_margin: float,
     *     source: string
     * }
     */
    public static function resolve(Article $article): array
    {
        $resellerMargin = (float) ($article->standard_reseller_margin ?? 0);
        $minimumMargin = (float) ($article->minimum_margin ?? 0);

        if ($resellerMargin > 0 && $minimumMargin > 0) {
            return [
                'reseller_margin' => $resellerMargin,
                'minimum_margin' => $minimumMargin,
                'source' => 'Artikel-override',
            ];
        }

        return [
            'reseller_margin' => $resellerMargin > 0 ? $resellerMargin : self::DEFAULT_RESELLER_MARGIN,
            'minimum_margin' => $minimumMargin > 0 ? $minimumMargin : self::DEFAULT_MINIMUM_MARGIN,
            'source' => 'Standard',
        ];
    }
}
