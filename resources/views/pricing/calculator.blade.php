@extends('layouts.pricing')

@section('title', 'Priskalkylator · ' . $article->article_number)

@section('content')
<div class="max-w-5xl mx-auto p-6"
     x-data='priceCalculator(@json([
        "articleNumber" => $article->article_number,
        "articleName" => $article->description,
        "apiKey" => $apiKey,
        "initial" => $initial,
     ]))'
     x-init="init()">

    {{-- Article header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ $article->description }}</h1>
        <div class="text-sm text-gray-600 mt-1">
            {{ $article->article_number }}
            @if ($article->ean)
                &middot; EAN {{ $article->ean }}
            @endif
            @if ($article->article_type === 'Bundle')
                <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-800 rounded text-xs font-medium">Bundle</span>
            @endif
        </div>
    </div>

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
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase font-semibold">Min. vår marginal</div>
                <div class="text-lg font-bold mt-1" x-text="state.min_margin.toFixed(1) + '%'"></div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase font-semibold">ÅF-marginal</div>
                <div class="text-lg font-bold mt-1" x-text="state.standard_reseller_margin.toFixed(1) + '%'"></div>
                <div class="text-xs text-gray-500" x-text="state.margin_source"></div>
            </div>
        </div>

        {{-- Sliders --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">RRP inkl. moms (SEK)</label>
                <input type="range"
                       :min="Math.round(state.cost * 1.25)"
                       :max="Math.max(5000, Math.round(state.rrp_inc_sek * 2))"
                       :value="state.rrp_inc_sek"
                       @input="onSliderInput('rrp', $event.target.valueAsNumber)"
                       class="w-full">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span x-text="formatCur('SEK', state.rrp_inc_sek)"></span>
                    <span x-text="'ex: ' + formatCur('SEK', state.rrp_ex_sek)"></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Vår marginal (%)</label>
                <input type="range" min="0" max="65" step="0.5"
                       :value="state.our_margin"
                       @input="onSliderInput('margin', $event.target.valueAsNumber)"
                       class="w-full">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span :class="state.below_min_margin ? 'text-red-700' : state.our_margin >= 20 ? 'text-green-700' : ''"
                          x-text="state.our_margin.toFixed(1) + '%'"></span>
                    <span x-text="formatCur('SEK', state.brutto)"></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">ÅF-marginal vid RRP (%)</label>
                <input type="range" min="5" max="65" step="0.5"
                       :value="state.reseller_margin"
                       @input="onSliderInput('reseller', $event.target.valueAsNumber)"
                       class="w-full">
                <div class="flex justify-between text-xs font-bold mt-1">
                    <span x-text="state.reseller_margin.toFixed(1) + '%'"></span>
                    <span x-text="state.margin_source"></span>
                </div>
            </div>
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
function priceCalculator(config) {
    return {
        articleNumber: config.articleNumber,
        apiKey: config.apiKey,
        state: config.initial,
        lastAdjusted: '',
        _debounce: null,

        init() {
            // Already rendered with initial state from server
        },

        onSliderInput(source, value) {
            this.lastAdjusted = source;

            // Optimistic update so the UI feels instant
            if (source === 'rrp') {
                this.state.rrp_inc_sek = value;
                this.state.rrp_ex_sek = value / 1.25;
            } else if (source === 'margin') {
                this.state.our_margin = value;
            } else if (source === 'reseller') {
                this.state.reseller_margin = value;
            }

            // Debounced recalc on the server for accurate numbers
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => this.recalc(source, value), 150);
        },

        async recalc(source, value) {
            const body = { source };
            body.rrp_ex_sek = this.state.rrp_ex_sek;
            body.our_margin = this.state.our_margin;
            body.reseller_margin = this.state.reseller_margin;

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

        formatCur(currency, value) {
            if (value === undefined || value === null || isNaN(value)) return '-';
            if (currency === 'EUR' || currency === 'USD') {
                return value.toFixed(2) + ' ' + currency;
            }
            return Math.round(value) + ' kr';
        },
    };
}
</script>
@endsection
