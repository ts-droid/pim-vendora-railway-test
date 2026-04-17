@extends('layouts.pricing')

@section('title', 'Leverantörer · Admin Database')

@section('content')
@include('admin._header', ['rightLabel' => '<span class="text-gray-500">Admin Database</span>'])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Leverantörer</h1>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($totalCount, 0, ',', ' ') }} leverantörer · visar max 50</p>
        </div>
    </div>

    <form method="get" action="/admin/suppliers" class="mb-4">
        <input type="hidden" name="api_key" value="{{ $apiKey }}">
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ $q }}"
                   placeholder="Sök på leverantörsnummer, namn eller org.nr…"
                   class="border rounded px-3 py-2 text-sm flex-1 focus:outline-none focus:border-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">Sök</button>
            @if ($q !== '')
                <a href="/admin/suppliers?api_key={{ urlencode($apiKey) }}"
                   class="border rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Rensa</a>
            @endif
        </div>
    </form>

    <div class="bg-white border rounded overflow-hidden">
        @if ($suppliers->isEmpty())
            <div class="p-12 text-center text-sm text-gray-500">
                @if ($q !== '')
                    Inga träffar för <strong>{{ $q }}</strong>.
                @else
                    Inga leverantörer i databasen.
                @endif
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Nr</th>
                        <th class="px-3 py-2 text-left font-semibold">Namn</th>
                        <th class="px-3 py-2 text-left font-semibold">Land</th>
                        <th class="px-3 py-2 text-left font-semibold">Org.nr</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($suppliers as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 font-mono">
                                <a href="/admin/suppliers/{{ rawurlencode($s->number) }}?api_key={{ urlencode($apiKey) }}"
                                   class="text-blue-600 hover:underline">{{ $s->number }}</a>
                            </td>
                            <td class="px-3 py-1.5 text-gray-700">{{ $s->name }}</td>
                            <td class="px-3 py-1.5 text-gray-600">{{ $s->country ?: '—' }}</td>
                            <td class="px-3 py-1.5 text-gray-600 font-mono text-xs">{{ $s->org_number ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>
@endsection
