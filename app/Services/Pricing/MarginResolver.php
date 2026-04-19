<?php

namespace App\Services\Pricing;

use App\Models\Article;
use App\Models\MarginRule;

/**
 * Resolve the effective reseller margin + minimum margin for an article
 * by walking the cascade (most-specific wins):
 *
 *   1. Article override  (articles.standard_reseller_margin > 0)
 *   2. Brand + category  (MarginRule matching both)
 *   3. Brand only        (MarginRule where brand=X, category_id IS NULL)
 *   4. Category only     (MarginRule where brand IS NULL, category_id=Y)
 *   5. Global default    (MarginRule where both NULL)
 *   6. Constant fallback (DEFAULT_RESELLER_MARGIN / DEFAULT_MINIMUM_MARGIN)
 */
class MarginResolver
{
    public const DEFAULT_RESELLER_MARGIN = 25.0;
    public const DEFAULT_MINIMUM_MARGIN = 18.0;

    /**
     * Back-compat shape — existing callers expect this schema.
     *
     * @return array{reseller_margin: float, minimum_margin: float, source: string}
     */
    public static function resolve(Article $article): array
    {
        $br = self::resolveBreakdown($article);
        return [
            'reseller_margin' => $br['reseller']['margin'],
            'minimum_margin' => $br['minimum']['margin'],
            'source' => self::labelForSource($br['reseller']['source']),
        ];
    }

    /**
     * Per-field breakdown with the rule that matched (for UI trace).
     *
     * @return array{
     *   reseller: array{margin: float, source: string, rule_id: ?int, matched_brand: ?string, matched_category_id: ?int},
     *   minimum:  array{margin: float, source: string, rule_id: ?int, matched_brand: ?string, matched_category_id: ?int},
     * }
     */
    public static function resolveBreakdown(Article $article): array
    {
        $brand = $article->brand ?: null;
        $categoryIds = self::extractCategoryIds($article);
        $rules = MarginRule::all();

        return [
            'reseller' => self::resolveField($article, 'standard_reseller_margin', 'reseller_margin', $brand, $categoryIds, $rules, self::DEFAULT_RESELLER_MARGIN),
            'minimum' => self::resolveField($article, 'minimum_margin', 'minimum_margin', $brand, $categoryIds, $rules, self::DEFAULT_MINIMUM_MARGIN),
        ];
    }

    private static function resolveField(
        Article $article,
        string $articleCol,
        string $ruleCol,
        ?string $brand,
        array $categoryIds,
        $rules,
        float $fallback
    ): array {
        $own = (float) ($article->{$articleCol} ?? 0);
        if ($own > 0) {
            return self::hit($own, 'article_override');
        }

        $relevantRules = $rules->filter(fn ($r) => ($r->{$ruleCol} ?? null) !== null);

        // 2. Brand + category (most specific)
        if ($brand && !empty($categoryIds)) {
            foreach ($relevantRules as $r) {
                if ($r->brand === $brand && in_array((int) $r->category_id, $categoryIds, true)) {
                    return self::fromRule($r, $ruleCol, 'brand_and_category');
                }
            }
        }

        // 3. Brand only
        if ($brand) {
            foreach ($relevantRules as $r) {
                if ($r->brand === $brand && $r->category_id === null) {
                    return self::fromRule($r, $ruleCol, 'brand');
                }
            }
        }

        // 4. Category only
        if (!empty($categoryIds)) {
            foreach ($relevantRules as $r) {
                if ($r->brand === null && in_array((int) $r->category_id, $categoryIds, true)) {
                    return self::fromRule($r, $ruleCol, 'category');
                }
            }
        }

        // 5. Global default rule
        foreach ($relevantRules as $r) {
            if ($r->brand === null && $r->category_id === null) {
                return self::fromRule($r, $ruleCol, 'global');
            }
        }

        return self::hit($fallback, 'default_constant');
    }

    private static function hit(float $margin, string $source): array
    {
        return [
            'margin' => $margin,
            'source' => $source,
            'rule_id' => null,
            'matched_brand' => null,
            'matched_category_id' => null,
        ];
    }

    private static function fromRule(MarginRule $r, string $ruleCol, string $source): array
    {
        return [
            'margin' => (float) ($r->{$ruleCol} ?? 0),
            'source' => $source,
            'rule_id' => (int) $r->id,
            'matched_brand' => $r->brand,
            'matched_category_id' => $r->category_id !== null ? (int) $r->category_id : null,
        ];
    }

    private static function extractCategoryIds(Article $article): array
    {
        $raw = $article->category_ids ?? null;
        if ($raw === null || $raw === '' || $raw === '[]') {
            return [];
        }
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /**
     * Mapping for the back-compat 'source' label used by existing callers.
     */
    private static function labelForSource(string $source): string
    {
        return match ($source) {
            'article_override' => 'Artikel-override',
            'brand_and_category' => 'Varumärke + kategori',
            'brand' => 'Varumärke',
            'category' => 'Kategori',
            'global' => 'Global marginalregel',
            default => 'Standard',
        };
    }
}
