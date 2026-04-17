@extends('layouts.pricing')

@section('title', $article->article_number . ' · Article Database')

@section('content')
{{-- Top bar (Vendora|Nordic look) --}}
<div class="bg-white border-b px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <span class="font-bold text-lg">VENDORA</span>
        <span class="text-red-500 font-bold">|</span>
        <span class="text-gray-500">NORDIC</span>
    </div>
    <div class="text-sm text-gray-700">
        <span class="font-semibold">{{ $article->article_number }}</span>
        <span class="text-gray-400">·</span>
        <span>Article Database</span>
    </div>
</div>

<div class="max-w-6xl mx-auto p-6">

    {{-- Back + Duplicate buttons row --}}
    <div class="flex justify-between items-center mb-4">
        <a href="javascript:history.back()" class="text-sm text-gray-600 hover:text-gray-900 inline-flex items-center gap-1">
            <span>←</span> Back
        </a>
        <button class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-1.5 rounded opacity-50 cursor-not-allowed"
                disabled title="Mocked — lives in adm.vendora.se">
            Duplicate article
        </button>
    </div>

    {{-- Tab bar --}}
    @php
        $tabLabels = [
            'general'   => 'General',
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
            'pricing'   => 'Pricing',
        ];
    @endphp
    <div class="flex border-b mb-6 overflow-x-auto">
        @foreach ($tabLabels as $key => $label)
            @php
                $isActive = $activeTab === $key;
                $baseClass = 'px-4 py-2 text-sm whitespace-nowrap';
                $activeClass = 'border-b-2 border-blue-500 text-blue-600 font-semibold';
                $inactiveClass = 'text-gray-500 hover:text-gray-900';
            @endphp
            <a href="?api_key={{ $apiKey }}&tab={{ $key }}"
               class="{{ $baseClass }} {{ $isActive ? $activeClass : $inactiveClass }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Tab content --}}
    @switch($activeTab)

        @case('pricing')
            @include('pricing._calculator')
            @break

        @case('general')
            <div class="bg-white border rounded p-6">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Status</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->status ?? 'Active' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Supplier</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->supplier_number ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Brand</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->brand ?? '—' }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article number</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->article_number }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article type</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->article_type }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">EAN</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $article->ean ?: '—' }}</div>
                    </div>

                    <div class="col-span-3">
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Article name</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $article->description }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Standard reseller margin</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ rtrim(rtrim(number_format((float) $article->standard_reseller_margin, 2), '0'), '.') }} %</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Minimum margin</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ rtrim(rtrim(number_format((float) $article->minimum_margin, 2), '0'), '.') }} %</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Average cost</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ rtrim(rtrim(number_format((float) $article->cost_price_avg, 2), '0'), '.') }} SEK</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">RRP (SEK)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $article->rek_price_SEK }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">RRP (EUR)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $article->rek_price_EUR, 2) }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">RRP (NOK)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $article->rek_price_NOK }}</div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t">
                    <strong>Note:</strong> This General tab is a read-only mock. The real editable form lives in adm.vendora.se.
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
