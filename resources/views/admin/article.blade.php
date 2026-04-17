@extends('layouts.pricing')

@section('title', $article->article_number . ' · Article Database')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($article->article_number) . '</span> <span class="text-gray-400">·</span> <span>Article Database</span>',
])

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

    @switch($activeTab)

        @case('pricing')
            @include('pricing._calculator')
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
                            <span>{{ rtrim(rtrim(number_format((float) ($article->external_cost ?? 0), 2), '0'), '.') ?: '0' }}</span>
                            <span class="text-gray-500">SEK</span>
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

            {{-- Margin-override section (from the legacy priskalkylator UI) --}}
            <div class="bg-white border rounded p-6 mt-6">
                <h3 class="text-sm font-semibold uppercase text-gray-500 mb-4">Artikelspecifika marginal-overrides</h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">ÅF-marginal override (%)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">
                            {{ rtrim(rtrim(number_format((float) $article->standard_reseller_margin, 2), '0'), '.') }} ({{ $article->brand ?: '—' }})
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Ärver: {{ rtrim(rtrim(number_format((float) $article->standard_reseller_margin, 2), '0'), '.') }}% från {{ $article->brand ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Min. vår marginal override (%)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">
                            {{ rtrim(rtrim(number_format((float) $article->minimum_margin, 2), '0'), '.') }} ({{ $article->brand ?: '—' }})
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Ärver: {{ rtrim(rtrim(number_format((float) $article->minimum_margin, 2), '0'), '.') }}% från {{ $article->brand ?: '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- BID-varianter (mocked) --}}
            <div class="bg-white border rounded p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">BID-varianter</h3>
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" disabled class="rounded cursor-not-allowed">
                            <span>Aktivera BID</span>
                        </label>
                        <button disabled title="Mocked" class="border rounded text-xs px-3 py-1 text-gray-600 opacity-50 cursor-not-allowed">+ Variant</button>
                    </div>
                </div>
                <div class="text-sm text-gray-500">Inga BID-varianter. Klicka "+ Variant" för att lägga till.</div>
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
