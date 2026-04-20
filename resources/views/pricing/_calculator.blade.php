{{--
    Reusable calculator partial.
    Required variables in scope:
      $article   (App\Models\Article)
      $calcConfig (array: articleNumber, articleName, apiKey, initial)

    Used by:
      - resources/views/pricing/calculator.blade.php (standalone)
      - resources/views/admin/article.blade.php     (Pricing tab)
--}}
<div x-data="priceCalculator({{ json_encode($calcConfig) }})" x-init="init()">

    {{-- Meta strip: per-currency RRP + ÅF-price --}}
    <div class="grid grid-cols-5 gap-2 mb-6">
        <template x-for="cur in ['SEK', 'EUR', 'NOK', 'DKK']" :key="cur">
            <div class="bg-white rounded border p-3 text-center">
                <div class="text-xs font-semibold text-gray-500 uppercase" x-text="'RRP ' + cur"></div>
                <div class="text-xl font-bold mt-1" x-text="formatCur(cur, state.currencies[cur]?.rrp_inc_rounded)"></div>
                <div class="text-xs text-gray-500 mt-2">ÅF-pris ex. moms</div>
                <div class="text-sm font-semibold text-green-700"
                     x-text="formatCur(cur, (state.currencies[cur]?.rrp_ex_rounded || 0) * (1 - state.reseller_margin / 100))">
                </div>
            </div>
        </template>
        <div class="bg-white rounded border p-3 text-center">
            <div class="text-xs font-semibold text-gray-500 uppercase">ÅF-marginal</div>
            <div class="text-xl font-bold mt-1" x-text="state.reseller_margin.toFixed(1) + '%'"></div>
            <div class="text-xs text-gray-500 mt-2" x-text="state.margin_source"></div>
        </div>
    </div>

    {{-- Cost overview grid (matches design screenshot) --}}
    <div class="bg-white rounded border p-4 mb-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold">Pris &amp; marginaler</h3>
            <span class="text-xs text-gray-500">slutpris = max(målpris, golvpris)</span>
        </div>
        <div class="grid grid-cols-4 gap-4">
            <template x-for="cur in ['SEK', 'EUR', 'NOK', 'DKK']" :key="cur">
                <div class="text-center">
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-1" x-text="cur"></div>
                    <div class="flex justify-between text-xs">
                        <div>
                            <div class="text-gray-500">Kostpris</div>
                            <div class="font-bold" x-text="formatCur(cur, cur === 'SEK' ? state.cost : (state.cost * (state.currencies[cur]?.rrp_inc_raw / (state.currencies.SEK?.rrp_inc_raw || 1))))"></div>
                        </div>
                        <div>
                            <div class="text-gray-500">Golvpris</div>
                            <div class="font-bold text-red-600" x-text="formatCur(cur, (cur === 'SEK' ? state.final_price_ex : state.final_price_ex * (state.currencies[cur]?.rrp_ex_rounded / (state.currencies.SEK?.rrp_ex_rounded || 1))))"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Calculator --}}
    <div class="bg-white rounded border p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Priskalkylator</h2>
            <span class="text-xs"
                  :class="state.rates_live ? 'text-green-700' : 'text-red-700'"
                  x-text="state.rates_live ? '● Live kurser' : '⚠ Estimerade kurser'"></span>
        </div>

        {{-- Read-only summary row --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Kostpris ex. moms</div>
                <div class="text-lg font-bold mt-1" x-text="formatCur('SEK', state.cost)"></div>
                <div class="text-xs text-gray-500">Standard</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Min. vår marginal</div>
                <div class="text-lg font-bold mt-1" x-text="state.min_margin.toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500" x-text="'Golvpris: ' + formatCur('SEK', state.final_price_ex)"></div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase font-semibold">ÅF-marginal</div>
                <div class="text-lg font-bold mt-1" x-text="state.standard_reseller_margin.toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500" x-text="state.margin_source"></div>
            </div>
        </div>

        {{-- Sliders med lås-checkboxar. Låst fält fryses efter recalc så
             man kan justera ett annat fält utan att det låsta ändras. --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div>
                <label class="flex justify-between items-center text-xs font-semibold text-gray-700 mb-1">
                    <span>RRP inkl. moms (SEK)</span>
                    <label class="inline-flex items-center gap-1 text-gray-500 cursor-pointer">
                        <input type="checkbox" x-model="locks.rrp" class="rounded">
                        <span x-text="locks.rrp ? '🔒' : '🔓'"></span>
                    </label>
                </label>
                <input type="range"
                       :min="Math.max(1, Math.round(state.cost * 1.25))"
                       :max="rrpSliderMax()"
                       :value="state.rrp_inc_sek"
                       :disabled="locks.rrp"
                       @input="onSliderInput('rrp', $event.target.valueAsNumber)"
                       :class="locks.rrp ? 'w-full opacity-50 cursor-not-allowed' : 'w-full'">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span x-text="formatCur('SEK', state.rrp_inc_sek)"></span>
                    <span x-text="'ex: ' + formatCur('SEK', state.rrp_ex_sek)"></span>
                </div>
            </div>
            <div>
                <label class="flex justify-between items-center text-xs font-semibold text-gray-700 mb-1">
                    <span>Vår marginal (%)</span>
                    <label class="inline-flex items-center gap-1 text-gray-500 cursor-pointer">
                        <input type="checkbox" x-model="locks.margin" class="rounded">
                        <span x-text="locks.margin ? '🔒' : '🔓'"></span>
                    </label>
                </label>
                <input type="range" min="0" max="65" step="0.5"
                       :value="state.our_margin"
                       :disabled="locks.margin"
                       @input="onSliderInput('margin', $event.target.valueAsNumber)"
                       :class="locks.margin ? 'w-full opacity-50 cursor-not-allowed' : 'w-full'">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span :class="state.below_min_margin ? 'text-red-700' : state.our_margin >= 20 ? 'text-green-700' : ''"
                          x-text="state.our_margin.toFixed(1) + '%'"></span>
                    <span x-text="formatCur('SEK', state.brutto)"></span>
                </div>
            </div>
            <div>
                <label class="flex justify-between items-center text-xs font-semibold text-gray-700 mb-1">
                    <span>ÅF-marginal vid RRP (%)</span>
                    <label class="inline-flex items-center gap-1 text-gray-500 cursor-pointer">
                        <input type="checkbox" x-model="locks.reseller" class="rounded">
                        <span x-text="locks.reseller ? '🔒' : '🔓'"></span>
                    </label>
                </label>
                <input type="range" min="5" max="65" step="0.5"
                       :value="state.reseller_margin"
                       :disabled="locks.reseller"
                       @input="onSliderInput('reseller', $event.target.valueAsNumber)"
                       :class="locks.reseller ? 'w-full opacity-50 cursor-not-allowed' : 'w-full'">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span x-text="state.reseller_margin.toFixed(1) + '%'"></span>
                    <span x-text="state.margin_source"></span>
                </div>
            </div>
        </div>

        {{-- Save button + flash feedback --}}
        <div class="flex justify-between items-center pt-4 border-t mt-4">
            <div class="text-xs"
                 :class="saveState === 'saved' ? 'text-green-700' : (saveState === 'error' ? 'text-red-700' : 'text-gray-500')"
                 x-text="saveMessage"></div>
            <button type="button"
                    @click="savePricing()"
                    :disabled="saveState === 'saving'"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm px-5 py-2 rounded disabled:opacity-50">
                <span x-text="saveState === 'saving' ? 'Sparar…' : 'Spara priser på artikelkortet'"></span>
            </button>
        </div>

        {{-- Currency input grid --}}
        <div class="grid grid-cols-4 gap-2 mb-4">
            <template x-for="cur in ['SEK', 'EUR', 'NOK', 'DKK']" :key="cur">
                <div class="bg-gray-50 rounded border p-2 text-center">
                    <div class="text-xs text-gray-500">RRP <span x-text="cur"></span></div>
                    <div class="text-base font-bold" x-text="formatCur(cur, state.currencies[cur]?.rrp_inc_rounded)"></div>
                    <div class="text-xs text-gray-500 mt-1"
                         x-text="state.currencies[cur] && state.currencies[cur].rrp_inc_rounded !== state.currencies[cur].rrp_inc_raw
                                 ? 'rå: ' + formatCur(cur, state.currencies[cur].rrp_inc_raw) : ''"></div>
                </div>
            </template>
        </div>

        {{-- Result boxes --}}
        <div class="grid grid-cols-3 gap-2">
            <div class="rounded border p-3 text-center"
                 :class="state.below_min_margin ? 'bg-red-50 border-red-200' : 'bg-white'">
                <div class="text-xs text-gray-500 font-semibold uppercase">Slutpris ex.</div>
                <div class="text-lg font-bold" x-text="formatCur('SEK', state.final_price_ex)"></div>
                <div class="text-xs" x-text="state.below_min_margin ? 'Under minimimarginal!' : 'OK'"></div>
            </div>
            <div class="rounded border p-3 text-center"
                 :class="state.below_min_margin ? 'bg-red-50 border-red-200' : state.our_margin >= 20 ? 'bg-green-50 border-green-200' : 'bg-white'">
                <div class="text-xs text-gray-500 font-semibold uppercase">Vår marginal</div>
                <div class="text-lg font-bold" x-text="state.our_margin.toFixed(1) + '%'"></div>
                <div class="text-xs" x-text="formatCur('SEK', state.brutto) + ' brutto'"></div>
            </div>
            <div class="bg-white rounded border p-3 text-center">
                <div class="text-xs text-gray-500 font-semibold uppercase">ÅF vid RRP</div>
                <div class="text-lg font-bold" x-text="state.reseller_margin.toFixed(1) + '%'"></div>
            </div>
        </div>
    </div>
</div>

<script>
if (!window.priceCalculator) {
    window.priceCalculator = function(config) {
        return {
            articleNumber: config.articleNumber,
            apiKey: config.apiKey,
            state: config.initial,
            lastAdjusted: '',
            _debounce: null,

            // Lås-flaggor för de tre reglagen. Ett låst fält fryses
            // efter varje recalc — man kan justera ett annat fält
            // utan att det låsta ändras från server-derivering.
            locks: { rrp: false, margin: false, reseller: false },

            // Spara-status för flash feedback i UI:et.
            saveState: '',
            saveMessage: '',

            init() {},

            onSliderInput(source, value) {
                if (this.locks[source]) return; // slider är låst, ignorera
                this.lastAdjusted = source;

                if (source === 'rrp') {
                    this.state.rrp_inc_sek = value;
                    this.state.rrp_ex_sek = value / 1.25;
                } else if (source === 'margin') {
                    this.state.our_margin = value;
                } else if (source === 'reseller') {
                    this.state.reseller_margin = value;
                }

                clearTimeout(this._debounce);
                this._debounce = setTimeout(() => this.recalc(source, value), 150);
            },

            async recalc(source, value) {
                // Servern deriverar nu rätt fält från (source, locks)
                // så vi skickar med locks och litar på servermattet
                // istället för att overrida låsta värden client-side.
                const body = {
                    source,
                    rrp_ex_sek: this.state.rrp_ex_sek,
                    our_margin: this.state.our_margin,
                    reseller_margin: this.state.reseller_margin,
                    locks: {
                        rrp: !!this.locks.rrp,
                        margin: !!this.locks.margin,
                        reseller: !!this.locks.reseller,
                    },
                };

                try {
                    const res = await fetch(
                        `/api/v1/price-calculator/${encodeURIComponent(this.articleNumber)}/calculate?api_key=${this.apiKey}`,
                        {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(body),
                        }
                    );
                    const data = await res.json();
                    if (data.success) {
                        this.state = data.data;
                    }
                } catch (e) {
                    console.error('recalc failed', e);
                }
            },

            async savePricing() {
                this.saveState = 'saving';
                this.saveMessage = '';

                const body = {
                    rek_price_SEK: Math.round(this.state.currencies?.SEK?.rrp_inc_rounded ?? this.state.rrp_inc_sek ?? 0),
                    rek_price_EUR: this.state.currencies?.EUR?.rrp_inc_rounded ?? 0,
                    rek_price_NOK: this.state.currencies?.NOK?.rrp_inc_rounded ?? 0,
                    rek_price_DKK: this.state.currencies?.DKK?.rrp_inc_rounded ?? 0,
                    standard_reseller_margin: this.state.reseller_margin,
                    minimum_margin: this.state.our_margin,
                };

                try {
                    const res = await fetch(
                        `/admin/articles/${encodeURIComponent(this.articleNumber)}/pricing/save?api_key=${this.apiKey}`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(body),
                        }
                    );
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.saveState = 'saved';
                        this.saveMessage = '✓ ' + (data.message || 'Sparat') + (data.saved_at ? ` (${data.saved_at})` : '');
                        setTimeout(() => {
                            if (this.saveState === 'saved') { this.saveState = ''; this.saveMessage = ''; }
                        }, 5000);
                    } else {
                        this.saveState = 'error';
                        this.saveMessage = 'Fel: ' + (data.message || res.statusText);
                    }
                } catch (e) {
                    console.error('save failed', e);
                    this.saveState = 'error';
                    this.saveMessage = 'Nätverksfel: ' + e.message;
                }
            },

            // RRP-slide-taket härleds från maxmarginalerna på de två
            // marginalsliderna (65 % vår marginal + 65 % ÅF-marginal).
            // basePriceEx_max = cost / (1 - 0.65)        (vår marginal på 65 %)
            // rrp_ex_max      = basePriceEx_max / (1 - 0.65)
            // rrp_inc_max     = rrp_ex_max × 1.25         (25 % moms)
            //                 = cost × 1.25 / 0.35²  ≈ cost × 10.204
            // Ger samma tak överallt på kalkylatorn — över det går
            // marginalsliderna inte högre ändå.
            rrpSliderMax() {
                const MARGIN_MAX = 0.65;
                const VAT = 1.25;
                const mult = VAT / ((1 - MARGIN_MAX) * (1 - MARGIN_MAX));
                const theoretical = Math.round((this.state.cost || 1) * mult);
                return Math.max(500, theoretical);
            },

            formatCur(currency, value) {
                if (value === undefined || value === null || isNaN(value)) return '-';
                if (currency === 'EUR' || currency === 'USD') {
                    return value.toFixed(2) + ' ' + currency;
                }
                return Math.round(value) + ' kr';
            },
        };
    }
}
</script>
