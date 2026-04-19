@extends('layouts.pricing')

@section('title', $article->article_number . ' · Article Database')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($article->article_number) . '</span> <span class="text-gray-400">·</span> <span>Article Database</span>',
])
@include('admin._nav', ['activeNav' => 'articles', 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <a href="javascript:history.back()" class="text-sm text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
            <span>←</span> Back
        </a>
        <button class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-1.5 rounded opacity-50 cursor-not-allowed"
                disabled title="Mocked — lives in adm.vendora.se">
            Duplicate article
        </button>
    </div>

    @php
        // Tab order — Pricing sits right after General per user preference.
        $tabLabels = [
            'general'   => 'General',
            'pricing'   => 'Pricing',
            'logistics' => 'Logistics',
            'web'       => 'Web',
            'images'    => 'Images',
            'files'     => 'Files',
            'reviews'   => 'Reviews',
            'campaign'  => 'Campaign',
            'google'    => 'Google',
            'raw'       => 'RAW',
            'faq'       => 'FAQ',
            'outlet'    => 'Outlet',
            'design'    => 'Design for / use cases',
        ];
    @endphp
    @include('admin._tabs', [
        'tabs' => $tabLabels,
        'queryPrefix' => 'api_key=' . urlencode($apiKey) . '&',
    ])

    @if (session('saved'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3 mb-4 text-sm">
            ✓ {{ session('saved') }}
        </div>
    @endif

    @switch($activeTab)

        @case('pricing')
            @include('pricing._calculator')

            {{-- Margin-override section — article overrides cascade down from brand defaults --}}
            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">Artikelspecifika marginal-overrides</h3>
                    @if ($brand && $article->brand)
                        <a href="/admin/brands/{{ rawurlencode($article->brand) }}?api_key={{ urlencode($apiKey) }}"
                           class="text-xs text-blue-600 hover:underline">
                            Redigera varumärkes-standard →
                        </a>
                    @endif
                </div>
                @php
                    $articleResellerVal = rtrim(rtrim(number_format((float) $article->standard_reseller_margin, 2), '0'), '.');
                    $articleMinVal      = rtrim(rtrim(number_format((float) $article->minimum_margin, 2), '0'), '.');
                    $brandReseller      = $brand?->standard_reseller_margin;
                    $brandMin           = $brand?->minimum_margin;
                @endphp
                <form method="POST" action="/admin/articles/{{ rawurlencode($article->article_number) }}/pricing?api_key={{ urlencode($apiKey) }}">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="art-std">ÅF-marginal override (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="standard_reseller_margin" id="art-std"
                                   value="{{ $articleResellerVal }}"
                                   class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                            <div class="text-xs text-gray-500 mt-1">
                                @if ($brandReseller !== null)
                                    Ärver: {{ rtrim(rtrim(number_format((float) $brandReseller, 2), '0'), '.') }}% från {{ $article->brand }} (varumärkes-standard)
                                @elseif ($article->brand)
                                    Ingen standard satt på varumärket <strong>{{ $article->brand }}</strong> — faller till global default.
                                @else
                                    Inget varumärke → global default.
                                @endif
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="art-min">Min. vår marginal override (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="minimum_margin" id="art-min"
                                   value="{{ $articleMinVal }}"
                                   class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                            <div class="text-xs text-gray-500 mt-1">
                                @if ($brandMin !== null)
                                    Ärver: {{ rtrim(rtrim(number_format((float) $brandMin, 2), '0'), '.') }}% från {{ $article->brand }} (varumärkes-standard)
                                @elseif ($article->brand)
                                    Ingen min-marginal satt på varumärket <strong>{{ $article->brand }}</strong> — faller till global default.
                                @else
                                    Inget varumärke → global default.
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-1.5 rounded">Spara marginaler</button>
                    </div>
                </form>
            </div>

            {{-- BID-varianter — varje variant ärver grunddata (namn, EAN, brand, kostnad) från artikeln ovan --}}
            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">BID-varianter</h3>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="/admin/articles/{{ rawurlencode($article->article_number) }}/bid/toggle?api_key={{ urlencode($apiKey) }}" class="inline">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox" name="bid_enabled" value="1" onchange="this.form.submit()"
                                       class="rounded"
                                       {{ $article->bid_enabled ? 'checked' : '' }}>
                                <span>Aktivera BID</span>
                            </label>
                        </form>
                        <form method="POST" action="/admin/articles/{{ rawurlencode($article->article_number) }}/bid/variants?api_key={{ urlencode($apiKey) }}" class="inline">
                            <button type="submit" class="border rounded text-xs px-3 py-1 text-gray-700 hover:bg-gray-50">+ Variant</button>
                        </form>
                    </div>
                </div>

                <div class="text-xs text-gray-500 mb-3">
                    Grunddata ärvs från artikeln ovan:
                    <span class="font-mono">{{ $article->article_number }}</span>
                    · {{ \Illuminate\Support\Str::limit($article->description, 50) }}
                    @if ($article->brand) · {{ $article->brand }} @endif
                </div>

                @if ($bidVariants->isEmpty())
                    <div class="text-sm text-gray-500 italic">Inga BID-varianter. Klicka "+ Variant" för att lägga till.</div>
                @else
                    <div class="space-y-3">
                        @foreach ($bidVariants as $v)
                            <form method="POST"
                                  action="/admin/articles/{{ rawurlencode($article->article_number) }}/bid/variants/{{ $v->id }}?api_key={{ urlencode($apiKey) }}"
                                  class="grid grid-cols-12 gap-2 items-end p-3 border rounded bg-gray-50">
                                <div class="col-span-4">
                                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Variant-SKU</label>
                                    <input type="text" name="variant_sku" value="{{ $v->variant_sku }}"
                                           placeholder="t.ex. {{ $article->article_number }}-BID01"
                                           class="border rounded px-2 py-1 text-sm w-full font-mono">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">BID-kostnad</label>
                                    <input type="number" step="0.01" min="0" name="cost" value="{{ rtrim(rtrim(number_format((float) $v->cost, 4, '.', ''), '0'), '.') ?: '0' }}"
                                           class="border rounded px-2 py-1 text-sm w-full text-right">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Fast pris</label>
                                    <input type="number" step="0.01" min="0" name="fixed_price" value="{{ rtrim(rtrim(number_format((float) $v->fixed_price, 4, '.', ''), '0'), '.') ?: '0' }}"
                                           class="border rounded px-2 py-1 text-sm w-full text-right">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Min. marginal %</label>
                                    <input type="number" step="0.01" min="0" max="100" name="min_margin" value="{{ rtrim(rtrim(number_format((float) $v->min_margin, 2, '.', ''), '0'), '.') ?: '0' }}"
                                           class="border rounded px-2 py-1 text-sm w-full text-right">
                                </div>
                                <div class="col-span-2 flex gap-1 justify-end">
                                    <button type="submit"
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs px-2.5 py-1 rounded">Spara</button>
                                    <button type="submit"
                                            formaction="/admin/articles/{{ rawurlencode($article->article_number) }}/bid/variants/{{ $v->id }}/delete?api_key={{ urlencode($apiKey) }}"
                                            onclick="return confirm('Ta bort variant {{ $v->variant_sku ?: $v->id }}?');"
                                            class="border border-red-300 text-red-600 hover:bg-red-50 text-xs px-2.5 py-1 rounded">Ta bort</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Stöd & kampanjer (mocked) --}}
            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">Stöd &amp; kampanjer</h3>
                    <button disabled title="Mocked" class="border rounded text-xs px-3 py-1 text-gray-600 opacity-50 cursor-not-allowed">+ Lägg till stöd</button>
                </div>
                <div class="text-sm text-gray-500">Inga stöd på artikelnivå.</div>
            </div>
            @break

        @case('general')
            <div class="bg-white border rounded p-6 space-y-6">

                {{-- Row: Status / Supplier / Brand --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Status <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->status ?? 'Active' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Supplier <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->supplier_number ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Brand <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->brand ?: '—' }}</div>
                    </div>
                </div>

                {{-- Row: Article number / Manufacturer article number --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article number <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->article_number }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Manufacturer article number <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->manufacturer_article_number ?? $article->article_number }}</div>
                    </div>
                </div>

                {{-- Row: EAN / UPC --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">EAN <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->ean ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">UPC code</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->upc_code ?: '—' }}</div>
                    </div>
                </div>

                {{-- Row: Article name / Article type --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article name</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->description }}</div>
                        <div class="text-xs text-gray-400 mt-1">Editable under "Web" tab.</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article type <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->article_type ?? 'Stock Item' }}</div>
                    </div>
                </div>

                {{-- Row: KN code / UN code --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">KN code <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->kn_code ?? '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">UN code</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->un_code ?: '—' }}</div>
                    </div>
                </div>

                {{-- Row: Country of origin / Lot / Serial Number Management --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Country of origin <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->country_of_origin ?? '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Lot / Serial Number Management</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->serial_number_management ?: 'No' }}</div>
                    </div>
                </div>

                {{-- Row: Standard reseller margin / Minimum margin --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Standard reseller margin</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) $article->standard_reseller_margin, 2), '0'), '.') }}</span>
                            <span class="text-gray-500">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Minimum margin</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) $article->minimum_margin, 2), '0'), '.') }}</span>
                            <span class="text-gray-500">%</span>
                        </div>
                    </div>
                </div>

                {{-- Row: RRP per currency (disabled — edited in Pricing tab) --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-500 uppercase font-semibold">RRP (editable in Pricing tab)</span>
                        <a href="?api_key={{ urlencode($apiKey) }}&tab=pricing" class="text-xs text-blue-600 hover:underline">Open Pricing tab →</a>
                    </div>
                    <div class="grid grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 font-semibold mb-1">RRP (SEK)</label>
                            <div class="border rounded px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">{{ (int) $article->rek_price_SEK }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 font-semibold mb-1">RRP (EUR)</label>
                            <div class="border rounded px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">{{ number_format((float) $article->rek_price_EUR, 2) }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 font-semibold mb-1">RRP (DKK)</label>
                            <div class="border rounded px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">{{ (int) $article->rek_price_DKK }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 font-semibold mb-1">RRP (NOK)</label>
                            <div class="border rounded px-3 py-2 bg-gray-100 text-gray-500 cursor-not-allowed">{{ (int) $article->rek_price_NOK }}</div>
                        </div>
                    </div>
                </div>

                {{-- Row: Cost stats (last/avg/highest/lowest) --}}
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Last cost</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) ($article->stats_last_cost ?? 0), 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Average cost</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) $article->cost_price_avg, 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Highest cost</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) ($article->stats_max_cost ?? 0), 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Lowest cost</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format((float) ($article->stats_min_cost ?? 0), 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
                        </div>
                    </div>
                </div>

                {{-- Row: Current cost / Lead time / ETA / Total sales --}}
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Current cost <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            @if ($supplierPrice)
                                <span>{{ rtrim(rtrim(number_format((float) $supplierPrice->price, 2), '0'), '.') ?: '0' }}</span>
                                <span class="text-gray-500">{{ $supplierPrice->currency }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                                <span class="text-gray-500">—</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Leverantörens inköpspris i deras valuta.
                            @if ($supplierPrice)
                                Källa: <code class="bg-gray-100 px-1 rounded">supplier_article_prices</code>
                            @else
                                Ingen rad i <code class="bg-gray-100 px-1 rounded">supplier_article_prices</code> för artikeln.
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Lead time</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>—</span>
                            <span class="text-gray-500">days</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">ETA next shipment</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>—</span>
                            <span class="text-gray-500">days</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Total sales (pcs)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ (int) ($article->total_sales ?? 0) }}</div>
                    </div>
                </div>

                {{-- Row: Last purchase + per-year sales --}}
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Last purchase</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->last_purchase_date ? \Carbon\Carbon::parse($article->last_purchase_date)->format('Y-m-d') : '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">{{ date('Y') }} (pcs)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">—</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">{{ date('Y') - 1 }} (pcs)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">—</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">{{ date('Y') - 2 }} (pcs)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">—</div>
                    </div>
                </div>

                {{-- Save buttons (mocked) --}}
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save</button>
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save &amp; back</button>
                </div>
                <div class="text-xs text-gray-400 text-right">
                    Last updated: {{ $article->updated_at ?? '—' }}
                </div>
            </div>

            @break

        @default
            <div class="bg-white border rounded p-12 text-center">
                <div class="text-5xl mb-3">🚧</div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ $tabLabels[$activeTab] }}</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    This tab is not implemented in the pim-vendora demo —
                    it lives in the <code class="bg-gray-100 px-1 rounded">adm.vendora.se</code> admin SPA.
                    Switch to <strong>General</strong> or <strong>Pricing</strong> to see working content.
                </p>
            </div>
    @endswitch
</div>
@endsection
