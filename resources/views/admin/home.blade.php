@extends('layouts.pricing')

@section('title', 'Start · Admin Database')

@section('content')
@include('admin._header', ['rightLabel' => '<span class="text-gray-500">Admin Database</span>'])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800">Admin Database</h1>
        <p class="text-sm text-gray-500 mt-1">
            Mockade admin-vyer över importerad produktionsdata. Marginal-sparning aktiv mot
            Railway-DB:n (inga utgående syncar).
        </p>
    </div>

    <div class="grid md:grid-cols-2 gap-6">

        {{-- ARTICLES CARD --}}
        <div class="bg-white border rounded p-5">
            <div class="flex items-center justify-between mb-3">
                <a href="/admin/articles?api_key={{ urlencode($apiKey) }}" class="text-lg font-semibold text-gray-800 hover:text-blue-600">
                    📦 Artiklar
                </a>
                <span class="text-xs text-gray-400">{{ number_format($counts['articles'], 0, ',', ' ') }} st</span>
            </div>

            <form method="get" action="/admin/articles" class="mb-4">
                <input type="hidden" name="api_key" value="{{ $apiKey }}">
                <div class="flex gap-2">
                    <input type="text" name="q" placeholder="Sök på nummer, namn, brand, EAN…"
                           class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
                </div>
            </form>

            <div class="text-xs uppercase text-gray-400 font-semibold mb-2">Senast uppdaterade</div>
            <ul class="space-y-1">
                @foreach ($articles as $a)
                    <li>
                        <a href="/admin/articles/{{ rawurlencode($a->article_number) }}?api_key={{ urlencode($apiKey) }}"
                           class="block text-sm hover:bg-gray-50 rounded px-2 py-1">
                            <span class="font-mono text-blue-600">{{ $a->article_number }}</span>
                            <span class="text-gray-600 ml-2">{{ \Illuminate\Support\Str::limit($a->description, 42) }}</span>
                            @if ($a->brand)
                                <span class="text-xs text-gray-400 ml-2">· {{ $a->brand }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- CUSTOMERS CARD --}}
        <div class="bg-white border rounded p-5">
            <div class="flex items-center justify-between mb-3">
                <a href="/admin/customers?api_key={{ urlencode($apiKey) }}" class="text-lg font-semibold text-gray-800 hover:text-blue-600">
                    👥 Kunder
                </a>
                <span class="text-xs text-gray-400">{{ number_format($counts['customers'], 0, ',', ' ') }} st</span>
            </div>

            <form method="get" action="/admin/customers" class="mb-4">
                <input type="hidden" name="api_key" value="{{ $apiKey }}">
                <div class="flex gap-2">
                    <input type="text" name="q" placeholder="Sök på nummer, namn, VAT…"
                           class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
                </div>
            </form>

            <div class="text-xs uppercase text-gray-400 font-semibold mb-2">Topp-försäljning (30 dgr)</div>
            <ul class="space-y-1">
                @foreach ($customers as $c)
                    <li>
                        <a href="/admin/customers/{{ rawurlencode($c->customer_number) }}?api_key={{ urlencode($apiKey) }}"
                           class="flex justify-between text-sm hover:bg-gray-50 rounded px-2 py-1">
                            <span>
                                <span class="font-mono text-blue-600">{{ $c->customer_number }}</span>
                                <span class="text-gray-600 ml-2">{{ \Illuminate\Support\Str::limit($c->name, 30) }}</span>
                            </span>
                            @if ($c->sales_last_30_days)
                                <span class="text-xs text-gray-400">
                                    {{ number_format((float) $c->sales_last_30_days, 0, ',', ' ') }} kr
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- SUPPLIERS CARD --}}
        <div class="bg-white border rounded p-5">
            <div class="flex items-center justify-between mb-3">
                <a href="/admin/suppliers?api_key={{ urlencode($apiKey) }}" class="text-lg font-semibold text-gray-800 hover:text-blue-600">
                    🏭 Leverantörer
                </a>
                <span class="text-xs text-gray-400">{{ number_format($counts['suppliers'], 0, ',', ' ') }} st</span>
            </div>

            <form method="get" action="/admin/suppliers" class="mb-4">
                <input type="hidden" name="api_key" value="{{ $apiKey }}">
                <div class="flex gap-2">
                    <input type="text" name="q" placeholder="Sök på nummer, namn, org.nr…"
                           class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
                </div>
            </form>

            <div class="text-xs uppercase text-gray-400 font-semibold mb-2">A–Ö</div>
            <ul class="space-y-1">
                @foreach ($suppliers as $s)
                    <li>
                        <a href="/admin/suppliers/{{ rawurlencode($s->number) }}?api_key={{ urlencode($apiKey) }}"
                           class="block text-sm hover:bg-gray-50 rounded px-2 py-1">
                            <span class="font-mono text-blue-600">{{ $s->number }}</span>
                            <span class="text-gray-600 ml-2">{{ \Illuminate\Support\Str::limit($s->name, 32) }}</span>
                            @if ($s->country)
                                <span class="text-xs text-gray-400 ml-2">· {{ $s->country }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- BRANDS CARD --}}
        <div class="bg-white border rounded p-5">
            <div class="flex items-center justify-between mb-3">
                <a href="/admin/brands?api_key={{ urlencode($apiKey) }}" class="text-lg font-semibold text-gray-800 hover:text-blue-600">
                    🏷 Varumärken
                </a>
                <span class="text-xs text-gray-400">{{ number_format($counts['brands'], 0, ',', ' ') }} st</span>
            </div>

            <form method="get" action="/admin/brands" class="mb-4">
                <input type="hidden" name="api_key" value="{{ $apiKey }}">
                <div class="flex gap-2">
                    <input type="text" name="q" placeholder="Filtrera varumärke…"
                           class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
                </div>
            </form>

            <div class="text-xs uppercase text-gray-400 font-semibold mb-2">Topp (flest artiklar)</div>
            <ul class="space-y-1">
                @foreach ($brands as $b)
                    <li>
                        <a href="/admin/brands/{{ rawurlencode($b->brand) }}?api_key={{ urlencode($apiKey) }}"
                           class="flex justify-between text-sm hover:bg-gray-50 rounded px-2 py-1">
                            <span class="text-blue-600">{{ $b->brand }}</span>
                            <span class="text-xs text-gray-400">{{ number_format($b->article_count, 0, ',', ' ') }} art.</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

    </div>

</div>
@endsection
