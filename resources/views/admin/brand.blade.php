@extends('layouts.pricing')

@section('title', $brand->name . ' · Brand')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($brand->name) . '</span> <span class="text-gray-400">·</span> <span>Brand Database</span>',
])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <a href="/admin/brands?api_key={{ urlencode($apiKey) }}" class="text-sm text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
            <span>←</span> Alla varumärken
        </a>
    </div>

    @if (session('saved'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3 mb-4 text-sm flex items-center justify-between">
            <span>✓ {{ session('saved') }}</span>
        </div>
    @endif

    <div class="bg-white border rounded p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-800">{{ $brand->name }}</h1>
                <p class="text-xs text-gray-500 mt-1">{{ number_format($articleCount, 0, ',', ' ') }} artiklar ärver från detta varumärke</p>
            </div>
        </div>

        <form method="POST" action="/admin/brands/{{ rawurlencode($brand->name) }}?api_key={{ urlencode($apiKey) }}">
            <h3 class="text-sm font-semibold uppercase text-gray-500 mb-3">Standard-marginaler (cascade)</h3>
            <div class="grid grid-cols-2 gap-6 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="std">Standard ÅF-marginal (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="standard_reseller_margin" id="std"
                           value="{{ $brand->standard_reseller_margin !== null ? rtrim(rtrim(number_format($brand->standard_reseller_margin, 2), '0'), '.') : '' }}"
                           placeholder="— (ej satt)"
                           class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                    <div class="text-xs text-gray-500 mt-1">
                        Artiklar utan eget override ärver detta värde. Tomt = brand ärver inte till artiklar.
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="min">Min. vår marginal (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="minimum_margin" id="min"
                           value="{{ $brand->minimum_margin !== null ? rtrim(rtrim(number_format($brand->minimum_margin, 2), '0'), '.') : '' }}"
                           placeholder="— (ej satt)"
                           class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                    <div class="text-xs text-gray-500 mt-1">
                        Ger golvet. Tomt = ingen brand-nivå satt.
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-1.5 rounded">Spara</button>
            </div>
        </form>

        <div class="text-xs text-gray-400 mt-2 text-right">
            Last updated: {{ $brand->updated_at ?? '—' }}
        </div>
    </div>

    <div class="bg-white border rounded p-6">
        <h3 class="text-sm font-semibold uppercase text-gray-500 mb-3">Artiklar som ärver (top 15 senast uppdaterade)</h3>
        @if ($articles->isEmpty())
            <div class="text-sm text-gray-500 italic">Inga artiklar knutna till detta varumärke.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Artikel</th>
                        <th class="px-3 py-2 text-left font-semibold">Namn</th>
                        <th class="px-3 py-2 text-right font-semibold">ÅF-marginal</th>
                        <th class="px-3 py-2 text-right font-semibold">Min. vår</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($articles as $a)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 font-mono">
                                <a href="/admin/articles/{{ rawurlencode($a->article_number) }}?api_key={{ urlencode($apiKey) }}&tab=pricing"
                                   class="text-blue-600 hover:underline">{{ $a->article_number }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-gray-700">{{ \Illuminate\Support\Str::limit($a->description, 50) }}</td>
                            <td class="px-3 py-1.5 text-right text-gray-600">
                                {{ rtrim(rtrim(number_format((float) $a->standard_reseller_margin, 2), '0'), '.') }}%
                            </td>
                            <td class="px-3 py-1.5 text-right text-gray-600">
                                {{ rtrim(rtrim(number_format((float) $a->minimum_margin, 2), '0'), '.') }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@endsection
