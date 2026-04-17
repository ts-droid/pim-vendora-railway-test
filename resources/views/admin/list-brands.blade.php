@extends('layouts.pricing')

@section('title', 'Varumärken · Admin Database')

@section('content')
@include('admin._header', ['rightLabel' => '<span class="text-gray-500">Admin Database</span>'])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Varumärken</h1>
            <p class="text-xs text-gray-500 mt-1">
                Beräknas från fri strängkolumn <code class="bg-gray-100 px-1 rounded">articles.brand</code>.
                Ingen egen brand-tabell finns ännu.
            </p>
        </div>
    </div>

    <form method="get" action="/admin/brands" class="mb-4">
        <input type="hidden" name="api_key" value="{{ $apiKey }}">
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}"
                   placeholder="Filtrera varumärke…"
                   class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
            @if ($q !== '')
                <a href="/admin/brands?api_key={{ urlencode($apiKey) }}"
                   class="border rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Rensa</a>
            @endif
        </div>
    </form>

    <div class="bg-white border rounded overflow-hidden">
        @if ($brands->isEmpty())
            <div class="p-12 text-center text-sm text-gray-500">Inga varumärken.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Varumärke</th>
                        <th class="px-3 py-2 text-right font-semibold">Artiklar</th>
                        <th class="px-3 py-2 text-right font-semibold">Visa</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($brands as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 text-gray-700">
                                <a href="/admin/brands/{{ rawurlencode($b->brand) }}?api_key={{ urlencode($apiKey) }}"
                                   class="text-blue-600 hover:underline">{{ $b->brand }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-600">{{ number_format($b->article_count, 0, ',', ' ') }}</td>
                            <td class="px-3 py-1.5 text-right">
                                <a href="/admin/articles?api_key={{ urlencode($apiKey) }}&q={{ urlencode($b->brand) }}"
                                   class="text-blue-600 hover:underline text-xs">
                                    Artiklar →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="text-xs text-gray-500 mt-4 p-3 bg-amber-50 border border-amber-200 rounded">
        <strong>TODO:</strong> Lägg till riktig <code>brands</code>-tabell för att kunna hålla
        standard-marginal och min-marginal per varumärke (cascade ner till artiklar).
    </div>

</div>
@endsection
