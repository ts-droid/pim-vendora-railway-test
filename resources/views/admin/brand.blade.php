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
            <span>←</span> Tillbaka
        </a>
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold text-gray-800">{{ $brand->name }}</h1>
            <span class="bg-gray-100 text-gray-700 text-xs px-2.5 py-0.5 rounded">{{ number_format($articleCount, 0, ',', ' ') }} artiklar</span>
        </div>
    </div>

    @if (session('saved'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3 mb-4 text-sm flex items-center justify-between">
            <span>✓ {{ session('saved') }}</span>
        </div>
    @endif

    @include('admin._tabs', [
        'tabs' => [
            'overview' => 'Översikt',
            'margins'  => 'Marginaler',
            'articles' => 'Alla produkter',
            'supports' => 'Stöd & kampanjer',
        ],
        'queryPrefix' => 'api_key=' . urlencode($apiKey) . '&',
    ])

    @switch($activeTab)

        @case('overview')
            {{-- Leverantörer --}}
            <div class="bg-white border rounded p-6 mb-6">
                <h3 class="text-sm font-semibold uppercase text-gray-500 mb-3">Leverantörer som bär detta varumärke</h3>
                @if ($suppliers->isEmpty())
                    <div class="text-sm text-gray-500 italic">Ingen leverantör har detta varumärke i <code class="bg-gray-100 px-1 rounded">suppliers.brand_name</code>.</div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Nr</th>
                                <th class="px-3 py-2 text-left font-semibold">Namn</th>
                                <th class="px-3 py-2 text-left font-semibold">Typ</th>
                                <th class="px-3 py-2 text-left font-semibold">Land</th>
                                <th class="px-3 py-2 text-left font-semibold">Valuta</th>
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
                                    <td class="px-3 py-1.5 text-gray-600">{{ $s->type ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-gray-600">{{ $s->country ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-gray-600 font-mono text-xs">{{ $s->currency ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Brand-level standard-marginaler --}}
            <div class="bg-white border rounded p-6">
                <h3 class="text-sm font-semibold uppercase text-gray-500 mb-3">Standardmarginaler för {{ $brand->name }}</h3>
                <form method="POST" action="/admin/brands/{{ rawurlencode($brand->name) }}?api_key={{ urlencode($apiKey) }}&tab=overview">
                    <div class="grid grid-cols-2 gap-6 mb-4">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="std">ÅF-marginal (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="standard_reseller_margin" id="std"
                                   value="{{ $brand->standard_reseller_margin !== null ? rtrim(rtrim(number_format($brand->standard_reseller_margin, 2), '0'), '.') : '' }}"
                                   placeholder="— (ej satt)"
                                   class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                            <div class="text-xs text-gray-500 mt-1">
                                Gäller alla {{ $brand->name }}-artiklar som inte har en kategorispecifik regel eller artikelspecifik override.
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase font-semibold mb-1" for="min">Min. vår marginal (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="minimum_margin" id="min"
                                   value="{{ $brand->minimum_margin !== null ? rtrim(rtrim(number_format($brand->minimum_margin, 2), '0'), '.') : '' }}"
                                   placeholder="— (ej satt)"
                                   class="border rounded px-3 py-2 w-full focus:outline-none focus:border-blue-500">
                            <div class="text-xs text-gray-500 mt-1">Golvet för detta varumärke.</div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-1.5 rounded">Spara</button>
                    </div>
                </form>
            </div>
            @break

        @case('margins')
            {{-- Per-kategori marginalregler för detta brand --}}
            <div class="bg-white border rounded p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">Kategorispecifika marginaler</h3>
                    <span class="text-xs text-gray-500">Mer specifik regel vinner över varumärkets standard.</span>
                </div>

                @if ($rules->isEmpty())
                    <div class="text-sm text-gray-500 italic mb-4">Inga kategoriregler än — alla {{ $brand->name }}-artiklar ärver varumärkets standardmarginaler.</div>
                @else
                    <table class="w-full text-sm mb-4">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Kategori</th>
                                <th class="px-3 py-2 text-right font-semibold">ÅF-marginal (%)</th>
                                <th class="px-3 py-2 text-right font-semibold">Min. vår (%)</th>
                                <th class="px-3 py-2 text-right font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($rules as $r)
                                <tr>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ $r->category?->title_sv ?? 'Alla kategorier (brand-default)' }}
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <form method="POST"
                                              action="/admin/brands/{{ rawurlencode($brand->name) }}/rules/{{ $r->id }}?api_key={{ urlencode($apiKey) }}"
                                              class="flex justify-end items-center gap-2">
                                            <input type="number" step="0.01" min="0" max="100" name="reseller_margin"
                                                   value="{{ $r->reseller_margin !== null ? rtrim(rtrim(number_format((float) $r->reseller_margin, 2), '0'), '.') : '' }}"
                                                   placeholder="—"
                                                   class="border rounded px-2 py-1 text-sm w-24 text-right">
                                            <input type="number" step="0.01" min="0" max="100" name="minimum_margin"
                                                   value="{{ $r->minimum_margin !== null ? rtrim(rtrim(number_format((float) $r->minimum_margin, 2), '0'), '.') : '' }}"
                                                   placeholder="—"
                                                   class="border rounded px-2 py-1 text-sm w-24 text-right">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs px-2.5 py-1 rounded">Spara</button>
                                            <button type="submit"
                                                    formaction="/admin/brands/{{ rawurlencode($brand->name) }}/rules/{{ $r->id }}/delete?api_key={{ urlencode($apiKey) }}"
                                                    onclick="return confirm('Ta bort regeln för {{ $r->category?->title_sv ?? 'brand' }}?');"
                                                    class="border border-red-300 text-red-600 hover:bg-red-50 text-xs px-2.5 py-1 rounded">Radera</button>
                                        </form>
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Lägg till ny kategoriregel --}}
                <form method="POST"
                      action="/admin/brands/{{ rawurlencode($brand->name) }}/rules?api_key={{ urlencode($apiKey) }}"
                      class="flex items-end gap-2 p-3 border border-dashed rounded">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Lägg till regel för kategori</label>
                        <select name="category_id" class="border rounded px-2 py-1.5 text-sm w-full">
                            <option value="">— Välj kategori —</option>
                            @foreach ($allCategoriesForPicker as $c)
                                <option value="{{ $c->id }}">{{ $c->title_sv }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-1.5 rounded">+ Kategoriregel</button>
                </form>

                @if ($uncoveredCategories->isNotEmpty())
                    <div class="mt-5 pt-4 border-t">
                        <div class="text-xs uppercase text-gray-500 font-semibold mb-2">
                            Kategorier utan egen regel ({{ $uncoveredCategories->count() }}) — ärver varumärkets standard
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($uncoveredCategories as $c)
                                <span class="bg-gray-100 border rounded-full text-xs px-2 py-0.5 text-gray-700">
                                    {{ $c['title'] }}
                                    <span class="text-gray-400">({{ $c['count'] }})</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @break

        @case('articles')
            <div class="bg-white border rounded p-6">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-sm font-semibold uppercase text-gray-500">Alla produkter ({{ number_format($articleCount, 0, ',', ' ') }})</h3>
                    <form method="get" action="/admin/brands/{{ rawurlencode($brand->name) }}" class="flex gap-2">
                        <input type="hidden" name="api_key" value="{{ $apiKey }}">
                        <input type="hidden" name="tab" value="articles">
                        <input type="text" name="q" value="{{ $articleSearch }}" placeholder="Sök artikelnummer eller namn…"
                               class="border rounded px-2 py-1 text-sm w-64">
                        <button type="submit" class="border rounded text-xs px-3 py-1 text-gray-700 hover:bg-gray-50">Sök</button>
                    </form>
                </div>

                @if ($articleRows->isEmpty())
                    <div class="text-sm text-gray-500 italic">Inga artiklar matchar sökningen.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Artikel</th>
                                    <th class="px-3 py-2 text-left font-semibold">SKU</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kategori</th>
                                    <th class="px-3 py-2 text-right font-semibold">Kostpris</th>
                                    <th class="px-3 py-2 text-right font-semibold">RRP ex.</th>
                                    <th class="px-3 py-2 text-right font-semibold">ÅF-marg.</th>
                                    <th class="px-3 py-2 text-left font-semibold">Källa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($articleRows as $row)
                                    @php
                                        $catTitle = $row['category_id'] ? ($articleCategories[$row['category_id']] ?? null) : null;
                                        $sourceLabel = match ($row['reseller_source']) {
                                            'article_override' => ['text' => 'artikel', 'class' => 'bg-amber-100 text-amber-700'],
                                            'brand_and_category' => ['text' => $brand->name . ' + kat', 'class' => 'bg-blue-100 text-blue-700'],
                                            'brand' => ['text' => $brand->name, 'class' => 'bg-blue-50 text-blue-700'],
                                            'category' => ['text' => 'kategori', 'class' => 'bg-gray-100 text-gray-700'],
                                            'global' => ['text' => 'global', 'class' => 'bg-gray-100 text-gray-700'],
                                            default => ['text' => 'default', 'class' => 'bg-gray-100 text-gray-500'],
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-1.5 text-gray-700 truncate max-w-xs">{{ \Illuminate\Support\Str::limit($row['description'], 38) }}</td>
                                        <td class="px-3 py-1.5 font-mono text-xs">
                                            <a href="/admin/articles/{{ rawurlencode($row['article_number']) }}?api_key={{ urlencode($apiKey) }}"
                                               class="text-blue-600 hover:underline">{{ $row['article_number'] }}</a>
                                        </td>
                                        <td class="px-3 py-1.5 text-xs">
                                            @if ($catTitle)
                                                <span class="bg-green-50 text-green-700 rounded px-1.5 py-0.5">{{ $catTitle }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-right text-gray-700">
                                            {{ rtrim(rtrim(number_format($row['cost_sek'], 2), '0'), '.') ?: '0' }} kr
                                        </td>
                                        <td class="px-3 py-1.5 text-right text-gray-700">
                                            {{ rtrim(rtrim(number_format($row['rek_price_SEK'], 2), '0'), '.') ?: '0' }} kr
                                        </td>
                                        <td class="px-3 py-1.5 text-right text-gray-700">
                                            {{ rtrim(rtrim(number_format($row['reseller_margin'], 1), '0'), '.') ?: '0' }}%
                                        </td>
                                        <td class="px-3 py-1.5">
                                            <span class="rounded px-2 py-0.5 text-xs font-semibold uppercase {{ $sourceLabel['class'] }}">
                                                {{ $sourceLabel['text'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-xs text-gray-400 mt-3 text-right">
                        Visar max 300 rader. ÅF-marginalen följer CostResolver + MarginResolver-cascaden — "Källa" visar var värdet kom ifrån.
                    </div>
                @endif
            </div>
            @break

        @case('supports')
            @php
                $statusBadge = function (string $status) {
                    return match ($status) {
                        'expired'      => ['text' => 'Utgången',    'class' => 'bg-gray-200 text-gray-600'],
                        'expires_soon' => ['text' => 'Snart slut',  'class' => 'bg-amber-100 text-amber-800'],
                        default        => ['text' => 'Aktiv',       'class' => 'bg-green-100 text-green-800'],
                    };
                };
                $renderTable = function ($items, $colorClass, $emptyLabel) use ($statusBadge) {
                    if ($items->isEmpty()) {
                        return '<div class="text-sm text-gray-500 italic">' . $emptyLabel . '</div>';
                    }
                    $html = '<table class="w-full text-sm">';
                    $html .= '<thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>';
                    $html .= '<th class="px-3 py-2 text-left font-semibold">Artikel</th>';
                    $html .= '<th class="px-3 py-2 text-left font-semibold">Typ</th>';
                    $html .= '<th class="px-3 py-2 text-right font-semibold">Belopp</th>';
                    $html .= '<th class="px-3 py-2 text-left font-semibold">Period</th>';
                    $html .= '<th class="px-3 py-2 text-left font-semibold">Status</th>';
                    $html .= '</tr></thead><tbody class="divide-y">';
                    foreach ($items as $r) {
                        $b = $statusBadge($r['status']);
                        $html .= '<tr class="hover:bg-gray-50">';
                        $html .= '<td class="px-3 py-1.5"><span class="font-mono text-xs text-blue-600">' . e($r['article_number']) . '</span><br><span class="text-xs text-gray-500">' . e(\Illuminate\Support\Str::limit($r['description'], 50)) . '</span></td>';
                        $html .= '<td class="px-3 py-1.5 text-xs text-gray-700">' . e(ucfirst($r['customer_type'])) . '</td>';
                        $html .= '<td class="px-3 py-1.5 text-right">' . e(rtrim(rtrim(number_format((float) $r['value'], 2), '0'), '.') ?: '0') . ' <span class="text-xs text-gray-500">' . e($r['unit_label']) . '</span></td>';
                        $html .= '<td class="px-3 py-1.5 text-xs text-gray-600">' . e($r['date_from'] ?: '—') . ' → ' . e($r['date_to'] ?: '—') . '</td>';
                        $html .= '<td class="px-3 py-1.5"><span class="rounded-full text-xs px-2 py-0.5 font-semibold ' . $b['class'] . '">' . e($b['text']) . '</span></td>';
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table>';
                    return $html;
                };
            @endphp

            <div class="bg-white border rounded p-6 mb-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Från leverantör (inkommande)</h3>
                        <p class="text-xs text-gray-500 mt-1">Det vi fakturerar tillbaka till varumärket/leverantören.</p>
                    </div>
                    <a href="/admin/brands/{{ rawurlencode($brand->name) }}/supports/export.csv?api_key={{ urlencode($apiKey) }}&direction=supplier"
                       class="border rounded text-xs px-3 py-1.5 text-gray-700 hover:bg-gray-50">
                        Exportera CSV
                    </a>
                </div>
                {!! $renderTable($supplierSupports, 'blue', 'Inga leverantörsstöd på artiklar i detta varumärke.') !!}
            </div>

            <div class="bg-white border rounded p-6 mb-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-sm font-semibold uppercase text-gray-500">Till kund (utgående)</h3>
                        <p class="text-xs text-gray-500 mt-1">Det vi krediterar / visar i prislistor till kund.</p>
                    </div>
                    <a href="/admin/brands/{{ rawurlencode($brand->name) }}/supports/export.csv?api_key={{ urlencode($apiKey) }}&direction=customer"
                       class="border rounded text-xs px-3 py-1.5 text-gray-700 hover:bg-gray-50">
                        Exportera CSV
                    </a>
                </div>
                {!! $renderTable($customerSupports, 'amber', 'Inga kundstöd / kampanjer på artiklar i detta varumärke.') !!}
            </div>

            @if ($supportsExpiringSoon->isNotEmpty())
                <div class="bg-amber-50 border border-amber-200 rounded p-4 text-sm">
                    <h4 class="font-semibold text-amber-900 mb-2">Stöd som löper ut inom 30 dagar ({{ $supportsExpiringSoon->count() }})</h4>
                    <ul class="space-y-1 text-xs text-amber-800">
                        @foreach ($supportsExpiringSoon as $r)
                            <li>
                                <span class="font-mono">{{ $r['article_number'] }}</span> —
                                {{ $r['layer_normalized'] === 'supplier' ? 'Från leverantör' : 'Till kund' }},
                                {{ rtrim(rtrim(number_format((float) $r['value'], 2), '0'), '.') ?: '0' }} {{ $r['unit_label'] }},
                                t.o.m. {{ $r['date_to'] }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="text-xs text-amber-700 mt-3 italic">
                        TODO: Mejl/Slack-notifikation när period avslutas — kräver cron + webhook-config.
                    </div>
                </div>
            @endif

            <div class="flex gap-2 mt-4">
                <a href="/admin/brands/{{ rawurlencode($brand->name) }}/supports/export.csv?api_key={{ urlencode($apiKey) }}&direction=all"
                   class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded">
                    Exportera alla stöd (CSV)
                </a>
            </div>
            @break

    @endswitch

</div>
@endsection
