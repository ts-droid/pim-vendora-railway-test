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

            {{-- Stöd & kampanjer — leverantörs- eller varumärkesstöd per artikel och kundtyp --}}
            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Stöd &amp; kampanjer</h3>
                        @if ($supplierCurrency && $supplierCurrency !== 'SEK')
                            <div class="text-xs text-gray-500 mt-1">
                                Leverantörens valuta: <span class="font-mono">{{ $supplierCurrency }}</span> — används som default på nya supplier-stöd.
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="/admin/articles/{{ rawurlencode($article->article_number) }}/supports?api_key={{ urlencode($apiKey) }}" class="inline">
                        <button type="submit" class="border rounded text-xs px-3 py-1 text-gray-700 hover:bg-gray-50">+ Lägg till stöd</button>
                    </form>
                </div>

                @if ($supports->isEmpty())
                    <div class="text-sm text-gray-500 italic">Inga stöd på artikelnivå.</div>
                @else
                    <div class="space-y-3">
                        @foreach ($supports as $s)
                            <form method="POST"
                                  action="/admin/articles/{{ rawurlencode($article->article_number) }}/supports/{{ $s->id }}?api_key={{ urlencode($apiKey) }}"
                                  class="p-3 border rounded bg-gray-50">
                                <div class="grid grid-cols-12 gap-3 items-start">
                                    <div class="col-span-3">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" title="Från leverantör = vi får in pengar/värde. Till kund = visas som rabatt i prislista.">Riktning</label>
                                        <select name="layer" class="border rounded px-2 py-1.5 text-sm w-full">
                                            <option value="supplier" {{ in_array($s->layer, ['supplier', 'brand']) ? 'selected' : '' }}>Från leverantör (inkommande)</option>
                                            <option value="customer" {{ $s->layer === 'customer' ? 'selected' : '' }}>Till kund (i prislista)</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Kundtyp</label>
                                        <select name="customer_type" class="border rounded px-2 py-1.5 text-sm w-full">
                                            <option value="upfront" {{ $s->customer_type === 'upfront' ? 'selected' : '' }}>Upfront</option>
                                            <option value="rebate" {{ $s->customer_type === 'rebate' ? 'selected' : '' }}>Rebate</option>
                                            <option value="other" {{ $s->customer_type === 'other' ? 'selected' : '' }}>Övrigt</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Värde</label>
                                        <input type="number" step="0.01" min="0" name="value" value="{{ rtrim(rtrim(number_format((float) $s->value, 4, '.', ''), '0'), '.') ?: '0' }}"
                                               class="border rounded px-2 py-1.5 text-sm w-full text-right">
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" title="% = procent. Annars valuta. Från leverantör = valuta ärvs från leverantörens valuta.">Enhet</label>
                                        @php
                                            $currentUnit = $s->is_percentage
                                                ? 'PCT'
                                                : strtoupper((string) ($s->currency ?: 'SEK'));
                                        @endphp
                                        <select name="unit" class="border rounded px-2 py-1.5 text-sm w-full">
                                            <option value="PCT" {{ $currentUnit === 'PCT' ? 'selected' : '' }}>%</option>
                                            @if ($supplierCurrency && $supplierCurrency !== 'SEK')
                                                <option value="SUPPLIER" {{ $currentUnit === $supplierCurrency ? 'selected' : '' }}>{{ $supplierCurrency }} (leverantör)</option>
                                            @endif
                                            <option value="SEK" {{ $currentUnit === 'SEK' ? 'selected' : '' }}>SEK</option>
                                            <option value="USD" {{ $currentUnit === 'USD' && $supplierCurrency !== 'USD' ? 'selected' : '' }}>USD</option>
                                            <option value="EUR" {{ $currentUnit === 'EUR' && $supplierCurrency !== 'EUR' ? 'selected' : '' }}>EUR</option>
                                            <option value="NOK" {{ $currentUnit === 'NOK' && $supplierCurrency !== 'NOK' ? 'selected' : '' }}>NOK</option>
                                            <option value="DKK" {{ $currentUnit === 'DKK' && $supplierCurrency !== 'DKK' ? 'selected' : '' }}>DKK</option>
                                            <option value="GBP" {{ $currentUnit === 'GBP' && $supplierCurrency !== 'GBP' ? 'selected' : '' }}>GBP</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Fr.o.m.</label>
                                        <input type="date" name="date_from" value="{{ $s->date_from?->format('Y-m-d') }}"
                                               class="border rounded px-2 py-1.5 text-sm w-full">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">T.o.m.</label>
                                        <input type="date" name="date_to" value="{{ $s->date_to?->format('Y-m-d') }}"
                                               class="border rounded px-2 py-1.5 text-sm w-full">
                                    </div>
                                </div>
                                <div class="flex gap-2 justify-end mt-3 pt-2 border-t">
                                    <button type="submit"
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1.5 rounded">Spara</button>
                                    <button type="submit"
                                            formaction="/admin/articles/{{ rawurlencode($article->article_number) }}/supports/{{ $s->id }}/delete?api_key={{ urlencode($apiKey) }}"
                                            onclick="return confirm('Ta bort stöd #{{ $s->id }}?');"
                                            class="border border-red-300 text-red-600 hover:bg-red-50 text-xs px-3 py-1.5 rounded">Ta bort</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
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

            {{-- Bundling — artikeln består av andra artiklar. Bundle-kostnad summeras via CostResolver (supplier-pris → SEK som fallback). Tillgänglig bundle = MIN(komponent-lager ÷ antal i bundle). --}}

            @if ($article->article_type !== 'Bundle' && $bundleComponents->isEmpty())
                {{-- Skapa-bundling-CTA visas bara när artikeln INTE redan är en bundle --}}
                <div class="bg-blue-50 border border-blue-200 rounded p-6 mt-6">
                    <h3 class="text-sm font-semibold uppercase text-gray-600 mb-2">Skapa bundlings-SKU</h3>
                    <p class="text-sm text-gray-700 mb-4">
                        Bygg en ny bundle där denna artikel ({{ $article->article_number }}) är första komponenten.
                        @if ($gs1Configured)
                            GTIN genereras automatiskt via GS1 Validoo när bundlen sparas.
                        @else
                            GS1 är ej konfigurerat — bundlen sparas utan EAN (kan genereras senare).
                        @endif
                    </p>
                    <form method="POST"
                          action="/admin/articles/{{ rawurlencode($article->article_number) }}/create-bundle?api_key={{ urlencode($apiKey) }}"
                          class="grid grid-cols-12 gap-2 items-end"
                          data-create-bundle-form
                          data-original-number="{{ $article->article_number }}"
                          data-original-description="{{ $article->description }}">
                        <div class="col-span-4">
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Bundle-SKU <span class="text-red-500">*</span></label>
                            <input type="text" name="bundle_article_number" required
                                   value="{{ $article->article_number }}"
                                   data-original-number-input
                                   class="border rounded px-2 py-1.5 text-sm w-full font-mono">
                        </div>
                        <div class="col-span-5">
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Beskrivning <span class="text-red-500">*</span></label>
                            <input type="text" name="bundle_description" required
                                   value="{{ $article->description }}"
                                   data-original-description-input
                                   class="border rounded px-2 py-1.5 text-sm w-full">
                        </div>
                        <div class="col-span-1">
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Antal</label>
                            <input type="number" name="first_component_quantity" value="1" min="1"
                                   class="border rounded px-2 py-1.5 text-sm w-full text-right">
                        </div>
                        <div class="col-span-2">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-2 rounded w-full">
                                Skapa bundle →
                            </button>
                        </div>
                        <div class="col-span-12 text-xs text-amber-700 hidden" data-unchanged-warning>
                            ⚠ Bundle-SKU och beskrivning måste ändras från grundartikelns värden innan du kan spara.
                        </div>
                    </form>

                    <script>
                        (() => {
                            const form = document.querySelector('[data-create-bundle-form]');
                            if (!form) return;
                            const numberEl = form.querySelector('[data-original-number-input]');
                            const descEl = form.querySelector('[data-original-description-input]');
                            const warn = form.querySelector('[data-unchanged-warning]');
                            const origNumber = form.dataset.originalNumber;
                            const origDesc = form.dataset.originalDescription;
                            form.addEventListener('submit', (e) => {
                                const unchangedNumber = numberEl.value.trim() === origNumber;
                                const unchangedDesc = descEl.value.trim() === origDesc;
                                if (unchangedNumber || unchangedDesc) {
                                    e.preventDefault();
                                    warn.classList.remove('hidden');
                                    if (unchangedNumber) numberEl.classList.add('border-amber-500');
                                    if (unchangedDesc) descEl.classList.add('border-amber-500');
                                }
                            });
                            [numberEl, descEl].forEach((el) => {
                                el.addEventListener('input', () => {
                                    el.classList.remove('border-amber-500');
                                    if (numberEl.value.trim() !== origNumber && descEl.value.trim() !== origDesc) {
                                        warn.classList.add('hidden');
                                    }
                                });
                            });
                        })();
                    </script>
                </div>
            @endif

            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">
                        Bundling
                        @if ($article->article_type === 'Bundle')
                            <span class="ml-2 bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded uppercase tracking-wide">Bundle</span>
                        @endif
                    </h3>
                </div>

                {{-- Summary-rad: kostnad + tillgänglighet + EAN + GS1-knapp --}}
                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Bundle-kostnad</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            <span>{{ rtrim(rtrim(number_format($bundleCost, 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Σ komponent-kostnader × antal. Fallback till leverantörens pris (USD/EUR/…) konverterat till SEK om average saknas.
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Tillgänglighet</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 flex justify-between">
                            @if ($bundleStock === null)
                                <span class="text-gray-400">—</span>
                                <span class="text-gray-500">ej bundle</span>
                            @else
                                <span class="{{ $bundleStock === 0 ? 'text-red-600' : '' }}">{{ $bundleStock }}</span>
                                <span class="text-gray-500">st</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            MIN(lager ÷ antal) över komponenterna. Begränsande komponent avgör.
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">EAN / GTIN</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->ean ?: '—' }}</div>
                        @if ($article->ean)
                            <div class="text-xs text-green-700 mt-1">✓ GTIN finns</div>
                        @else
                            <div class="text-xs text-gray-500 mt-1">Ingen EAN — generera via GS1 Validoo-knappen.</div>
                        @endif
                    </div>
                    <div class="flex items-end">
                        <form method="POST"
                              action="/admin/articles/{{ rawurlencode($article->article_number) }}/bundle/generate-gtin?api_key={{ urlencode($apiKey) }}"
                              class="w-full"
                              onsubmit="return confirm('Ringa GS1 Validoo och generera + aktivera en riktig GTIN nu?');">
                            <button type="submit"
                                    @if (!$gs1Configured) disabled title="GS1 ej konfigurerat: saknar GS1_API_KEY / GS1_COMPANY_PREFIX" @endif
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded {{ !$gs1Configured ? 'opacity-50 cursor-not-allowed' : '' }}">
                                Generera GTIN via GS1 →
                            </button>
                            <div class="text-xs text-gray-500 mt-1">
                                @if ($gs1Configured)
                                    Anropar services.validoo.se live.
                                @else
                                    <span class="text-amber-700">GS1 ej konfigurerat.</span>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Komponenter --}}
                <div class="border-t pt-4">
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-xs text-gray-500 uppercase font-semibold">Komponenter</h4>
                        <span class="text-xs text-gray-400">Grunddata ärvs från respektive komponent-artikel</span>
                    </div>

                    @if ($bundleComponents->isEmpty())
                        <div class="text-sm text-gray-500 italic mb-3">Inga komponenter. Lägg till nedan för att göra detta till en bundle.</div>
                    @else
                        <div class="space-y-2 mb-3">
                            @foreach ($bundleComponents as $bc)
                                @php
                                    $br = $componentCostBreakdowns[$bc->id] ?? ['sek' => 0, 'source' => 'none', 'raw_amount' => 0, 'raw_currency' => 'SEK'];
                                    $stockOH = (int) ($bc->component?->stock_on_hand ?? 0);
                                    $bundlesFromThis = (int) $bc->quantity > 0 ? intdiv($stockOH, (int) $bc->quantity) : 0;
                                @endphp
                                <form method="POST"
                                      action="/admin/articles/{{ rawurlencode($article->article_number) }}/bundle/components/{{ $bc->id }}?api_key={{ urlencode($apiKey) }}"
                                      class="grid grid-cols-12 gap-2 items-center p-2 border rounded bg-gray-50">
                                    <div class="col-span-2 font-mono text-sm">
                                        <a href="/admin/articles/{{ rawurlencode($bc->component_article_number) }}?api_key={{ urlencode($apiKey) }}"
                                           class="text-blue-600 hover:underline">{{ $bc->component_article_number }}</a>
                                    </div>
                                    <div class="col-span-4 text-sm text-gray-700 truncate">
                                        {{ $bc->component?->description ?? '— artikel saknas —' }}
                                        @if ($bc->component?->brand)
                                            <span class="text-xs text-gray-400">· {{ $bc->component->brand }}</span>
                                        @endif
                                    </div>
                                    <div class="col-span-2 text-right text-xs">
                                        <span class="text-gray-700 font-medium">{{ rtrim(rtrim(number_format($br['sek'], 2), '0'), '.') ?: '0' }} kr</span>
                                        @if ($br['source'] === 'supplier_article_prices')
                                            <div class="text-[11px] text-amber-600">
                                                fb: {{ rtrim(rtrim(number_format($br['raw_amount'], 2), '0'), '.') }} {{ $br['raw_currency'] }}
                                            </div>
                                        @elseif ($br['source'] === 'cost_price_avg')
                                            <div class="text-[11px] text-gray-400">average</div>
                                        @elseif ($br['source'] === 'external_cost')
                                            <div class="text-[11px] text-gray-400">external</div>
                                        @else
                                            <div class="text-[11px] text-red-500">ingen kostnad</div>
                                        @endif
                                    </div>
                                    <div class="col-span-1 text-right text-xs text-gray-700">
                                        {{ $stockOH }} st
                                        <div class="text-[11px] {{ $bundlesFromThis === 0 ? 'text-red-600' : 'text-gray-400' }}">→ {{ $bundlesFromThis }} bd</div>
                                    </div>
                                    <div class="col-span-1">
                                        <input type="number" name="quantity" value="{{ $bc->quantity }}" min="1"
                                               class="border rounded px-2 py-1 text-sm w-full text-right">
                                    </div>
                                    <div class="col-span-2 flex gap-1 justify-end">
                                        <button type="submit"
                                                class="bg-green-600 hover:bg-green-700 text-white text-xs px-2.5 py-1 rounded">Spara</button>
                                        <button type="submit"
                                                formaction="/admin/articles/{{ rawurlencode($article->article_number) }}/bundle/components/{{ $bc->id }}/delete?api_key={{ urlencode($apiKey) }}"
                                                onclick="return confirm('Ta bort {{ $bc->component_article_number }} från bundlen?');"
                                                class="border border-red-300 text-red-600 hover:bg-red-50 text-xs px-2.5 py-1 rounded">Ta bort</button>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    {{-- Lägg till komponent --}}
                    <form method="POST"
                          action="/admin/articles/{{ rawurlencode($article->article_number) }}/bundle/components?api_key={{ urlencode($apiKey) }}"
                          class="grid grid-cols-12 gap-2 items-end p-2 border border-dashed rounded">
                        <div class="col-span-8">
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Komponent-artikelnummer</label>
                            <input type="text" name="component_article_number" required
                                   placeholder="t.ex. TS-2258"
                                   class="border rounded px-2 py-1 text-sm w-full font-mono">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Antal</label>
                            <input type="number" name="quantity" value="1" min="1"
                                   class="border rounded px-2 py-1 text-sm w-full text-right">
                        </div>
                        <div class="col-span-2 flex justify-end">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1.5 rounded w-full">+ Lägg till</button>
                        </div>
                    </form>
                </div>
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
