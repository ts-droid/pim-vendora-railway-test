@extends('layouts.pricing')

@section('title', 'Kunder · Admin Database')

@section('content')
@include('admin._header', ['rightLabel' => '<span class="text-gray-500">Admin Database</span>'])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Kunder</h1>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($totalCount, 0, ',', ' ') }} kunder · sorterade efter senaste 30 dagars försäljning · visar max 50</p>
        </div>
    </div>

    <form method="get" action="/admin/customers" class="mb-4">
        <input type="hidden" name="api_key" value="{{ $apiKey }}">
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}"
                   placeholder="Sök på kundnummer, namn, org.nr eller VAT-nr…"
                   class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
            @if ($q !== '')
                <a href="/admin/customers?api_key={{ urlencode($apiKey) }}"
                   class="border rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Rensa</a>
            @endif
        </div>
    </form>

    <div class="bg-white border rounded overflow-hidden">
        @if ($customers->isEmpty())
            <div class="p-12 text-center text-sm text-gray-500">
                @if ($q !== '')
                    Inga träffar för <strong>{{ $q }}</strong>.
                @else
                    Inga kunder i databasen.
                @endif
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Kundnr</th>
                        <th class="px-3 py-2 text-left font-semibold">Namn</th>
                        <th class="px-3 py-2 text-left font-semibold">Land</th>
                        <th class="px-3 py-2 text-left font-semibold">VAT</th>
                        <th class="px-3 py-2 text-right font-semibold">Försäljning 30d</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($customers as $c)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 font-mono">
                                <a href="/admin/customers/{{ rawurlencode($c->customer_number) }}?api_key={{ urlencode($apiKey) }}"
                                   class="text-blue-600 hover:underline">{{ $c->customer_number }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-gray-700">{{ $c->name }}</td>
                            <td class="px-3 py-1.5 text-gray-600">{{ $c->country ?: '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-600 font-mono text-xs">{{ $c->vat_number ?: '—' }}</td>
                            <td class="px-3 py-1.5 text-right text-gray-700">
                                {{ number_format((float) $c->sales_last_30_days, 0, ',', ' ') }} kr
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@endsection
