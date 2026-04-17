# Embedding the Priskalkylator into adm.vendora.se

The pim-vendora service exposes the price calculator in two equivalent forms:

## 1. A standalone page

```
GET /pricing/{articleNumber}?api_key=<key>
```

Full-page layout, no navigation. Useful as a direct link or iframe target.

## 2. A mocked admin article detail page

```
GET /admin/articles/{articleNumber}?api_key=<key>&tab=pricing
```

Vendora|Nordic-styled header, the full tab bar (General, Logistics, Web, …, Pricing), and the calculator rendered in the Pricing tab. The other tabs render a "mocked — lives in adm.vendora.se" placeholder. This is the demo surface — useful for showing how it *would* look when integrated.

## 3. The JSON API underneath

Both pages sit on top of these endpoints (same `api_key` auth):

```
GET  /api/v1/price-calculator/{articleNumber}
POST /api/v1/price-calculator/{articleNumber}/calculate
```

Response schema from both:

```json
{
    "success": true,
    "data": {
        "cost": 1033.44,
        "min_margin": 25.0,
        "standard_reseller_margin": 15.0,
        "margin_source": "Artikel-override",
        "rrp_ex_sek": 1599.20,
        "rrp_inc_sek": 1999,
        "final_price_ex": 1359.32,
        "our_margin": 23.98,
        "reseller_margin": 15.0,
        "brutto": 325.88,
        "below_min_margin": true,
        "currencies": {
            "SEK": { "rrp_inc_raw": 1999, "rrp_inc_rounded": 1999, "rrp_ex_rounded": 1599.2 },
            "EUR": { "rrp_inc_raw": 176.0, "rrp_inc_rounded": 179.99, "rrp_ex_rounded": 143.99 },
            "NOK": { "rrp_inc_raw": 2300, "rrp_inc_rounded": 2299, "rrp_ex_rounded": 1839.2 },
            "DKK": { "rrp_inc_raw": 1440, "rrp_inc_rounded": 1499, "rrp_ex_rounded": 1199.2 }
        },
        "rates_live": true
    }
}
```

`POST /calculate` takes any of `rrp_ex_sek`, `our_margin`, `reseller_margin` plus a `source: "rrp" | "margin" | "reseller"` hint so it knows which slider moved.

---

## Options for integrating into adm.vendora.se

Pick one based on how tightly you want the Pricing tab to live inside the existing SPA.

### Option A — Iframe (zero integration effort)

In the Article detail page in adm.vendora.se, the Pricing tab renders:

```html
<iframe
    src="https://pim-vendora.up.railway.app/pricing/${articleNumber}?api_key=${ADM_SHARED_KEY}"
    width="100%"
    height="900"
    frameborder="0"
    sandbox="allow-scripts allow-same-origin"
></iframe>
```

- **Pros:** No code change on the pim-vendora side. No coupling. Can ship behind a feature flag per user.
- **Cons:** Different styling (Tailwind vs whatever adm.vendora.se uses). Scrollbar and height jumps. Mixed analytics.

### Option B — Direct JSON integration (recommended)

Rewrite the Pricing tab natively in adm.vendora.se's SPA framework and call the two JSON endpoints. The Blade template at `resources/views/pricing/_calculator.blade.php` is small (~150 lines of markup + ~50 lines of Alpine) and can be ported to Vue/React/whatever adm.vendora.se uses in an afternoon.

- **Pros:** Native look and feel. Shared state with the rest of the page. No iframe issues.
- **Cons:** Requires the SPA team to implement. Visual regressions need testing against the Figma/screenshots.

Key implementation notes:

1. **Slider auto-lock.** When the user drags a slider, save it to `lastAdjusted` and pass that as `source` to `/calculate`. The backend preserves the last-adjusted value when another slider moves.
2. **Smart rounding** is server-side (`App\Services\Pricing\SmartRounder`). Don't re-implement — just display `currencies.SEK.rrp_inc_rounded` etc.
3. **Currency grid** shows both raw (from FX conversion) and rounded (psychological .99 endings) values. The delta between the two is shown as "rå: X" when they differ.
4. **Below-minimum-margin** is a derived boolean from the server — use it to style the result box red.
5. **Debounce** slider → recalc calls by 100–200 ms. The server response round-trips in ~30–80 ms.

### Option C — Server-side include (hybrid)

adm.vendora.se fetches `/admin/articles/{nr}?tab=pricing` server-side and injects just the `<div x-data="priceCalculator(...)">…</div>` block into its existing page. Alpine.js runs on the fragment, the rest of the page uses Vue/React.

- **Pros:** Minimal SPA work.
- **Cons:** Needs Alpine.js loaded in adm.vendora.se. Style collisions between Tailwind and the admin's CSS.

---

## Authentication

All pim-vendora endpoints accept an `api_key` query parameter (legacy pattern from `PurchaseOrderConfirm`, `EsignPublic`). The key is validated against the `api_keys` table.

For adm.vendora.se integration, generate a service-level key:

```sql
INSERT INTO api_keys (api_key, created_at, updated_at)
VALUES ('adm-vendora-se-service-key', NOW(), NOW());
```

…and store it in adm.vendora.se's config. Do not expose it client-side if going with option B — proxy through the SPA's backend.

## Rate limits

None currently. The EcbService caches currency rates for 10 h, so the calculator endpoint's only work per request is DB queries + margin math (typically < 20 ms).

## What's *not* production-ready

The Railway test instance (`deploy/railway-test` branch) is **locked down** — it has:

- `DISABLE_OUTBOUND_SYNCS=1` (no writes to VismaNet, MailerLite, Vendora Admin)
- `configs.wgr_is_active=0` forced at boot (defensive, blocks UpdateArticleJob)
- `APP_ENV=testing` (disables the Laravel scheduler)
- `MAIL_MAILER=log` (emails go to stderr)
- `QUEUE_CONNECTION=sync` (no background jobs)

Before shipping this to production as a feature in adm.vendora.se, remove these guards and verify the calculator does not accidentally trigger article syncs. The `Article::booted()::saved` hook in `app/Models/Article.php` already respects `DISABLE_OUTBOUND_SYNCS`; keep that.

## File reference

| Path | Purpose |
|------|---------|
| `app/Http/Controllers/PricingWebController.php` | Standalone `/pricing/{nr}` page |
| `app/Http/Controllers/AdminArticleController.php` | Mocked `/admin/articles/{nr}` with tabs |
| `app/Http/Controllers/PriceCalculatorController.php` | JSON API at `/api/v1/price-calculator/*` |
| `app/Services/Pricing/PriceCalculatorService.php` | Margin math, currency grid |
| `app/Services/Pricing/SmartRounder.php` | Psychological rounding |
| `app/Services/Pricing/CostResolver.php` | cost_price_avg → external_cost fallback, bundle recursion |
| `app/Services/Pricing/MarginResolver.php` | Reads standard_reseller_margin + minimum_margin |
| `resources/views/pricing/_calculator.blade.php` | The Alpine component (shared between both pages) |
| `resources/views/admin/article.blade.php` | Tabs layout, mocked General + Pricing tabs |
