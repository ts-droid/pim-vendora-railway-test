@extends('layouts.pricing')

@section('title', $customer->customer_number . ' · Customer Database')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($customer->customer_number) . '</span> <span class="text-gray-400">·</span> <span>Customer Database</span>',
])
@include('admin._nav', ['activeNav' => 'customers', 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <a href="javascript:history.back()" class="text-sm text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
            <span>←</span> Back
        </a>
    </div>

    @include('admin._tabs', [
        'tabs' => [
            'general'   => 'General',
            'contacts'  => 'Contacts',
            'pricelist' => 'Prislista',
            'logins'    => 'Web-inloggningar',
            'crm'       => 'CRM',
        ],
        'queryPrefix' => 'api_key=' . urlencode($apiKey) . '&',
    ])

    @switch($activeTab)

        @case('general')
            <div class="bg-white border rounded p-6">
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Customer number <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $customer->customer_number }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Org number</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $customer->org_number ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">VAT number</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $customer->vat_number ?: '—' }}</div>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Name <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $customer->name }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Country</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $customer->country ?: '—' }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Credit limit</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $customer->credit_limit, 2, ',', ' ') }} SEK</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Credit terms</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $customer->credit_terms }} days</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Credit balance</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $customer->credit_balance, 2, ',', ' ') }} SEK</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Sales (last 30 days)</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $customer->sales_last_30_days, 0, ',', ' ') }} SEK</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Vendora rating</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $customer->vendora_rating, 2) }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Return rate</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ number_format((float) $customer->return_rate, 2) }} %</div>
                    </div>

                    <div class="col-span-3">
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Shop URL</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 break-all">
                            @if ($customer->shop_url)
                                <a href="{{ $customer->shop_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $customer->shop_url }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save</button>
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save &amp; back</button>
                </div>

                <div class="text-xs text-gray-400 mt-2 text-right">
                    Last updated: {{ $customer->updated_at ?? '—' }}
                </div>
            </div>
            @break

        @case('pricelist')
            <div class="bg-white border rounded p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Kundspecifik prislista</h3>
                    <span class="text-xs text-gray-400">
                        Källa: <code class="bg-gray-100 px-1 rounded">article_prices</code>
                        (matchas på <code class="bg-gray-100 px-1 rounded">customer_id = {{ $customer->customer_number }}</code>)
                    </span>
                </div>

                @if ($pricelist->isEmpty())
                    <div class="border border-dashed rounded p-8 text-center text-sm text-gray-500">
                        Inga kundspecifika priser registrerade för den här kunden.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Article</th>
                                    <th class="px-3 py-2 text-right font-semibold">Base SEK</th>
                                    <th class="px-3 py-2 text-right font-semibold">Base EUR</th>
                                    <th class="px-3 py-2 text-right font-semibold">Base DKK</th>
                                    <th class="px-3 py-2 text-right font-semibold">Base NOK</th>
                                    <th class="px-3 py-2 text-right font-semibold">%</th>
                                    <th class="px-3 py-2 text-right font-semibold">% inner</th>
                                    <th class="px-3 py-2 text-right font-semibold">% master</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($pricelist as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-1.5 font-mono">{{ $row->article_number }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->base_price_SEK, 2, ',', ' ') }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->base_price_EUR, 2, ',', ' ') }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->base_price_DKK, 2, ',', ' ') }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->base_price_NOK, 2, ',', ' ') }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->percent, 2) }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->percent_inner, 2) }}</td>
                                        <td class="px-3 py-1.5 text-right">{{ number_format((float) $row->percent_master, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-xs text-gray-400 mt-3 text-right">
                        {{ $pricelist->count() }} rader · max 200 visas
                    </div>
                @endif
            </div>

            {{-- BID-artiklar tillgängliga för kunden --}}
            <div class="bg-white border rounded p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">BID-artiklar tillgängliga</h3>
                    <span class="text-xs text-gray-400">
                        BID-varianter på artiklar där offertpris är aktiverat
                    </span>
                </div>
                @if ($bidVariantsAvailable->isEmpty())
                    <div class="border border-dashed rounded p-8 text-center text-sm text-gray-500">
                        Inga BID-artiklar är aktiverade just nu.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Artikel</th>
                                <th class="px-3 py-2 text-left font-semibold">Variant-SKU</th>
                                <th class="px-3 py-2 text-left font-semibold">Varumärke</th>
                                <th class="px-3 py-2 text-right font-semibold">BID-kostnad</th>
                                <th class="px-3 py-2 text-right font-semibold">Fast pris</th>
                                <th class="px-3 py-2 text-right font-semibold">Min-marg %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($bidVariantsAvailable as $v)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5 font-mono text-xs">
                                        <a href="/admin/articles/{{ rawurlencode($v->article_number) }}?api_key={{ urlencode($apiKey) }}"
                                           class="text-blue-600 hover:underline">{{ $v->article_number }}</a>
                                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($v->article?->description ?? '', 40) }}</div>
                                    </td>
                                    <td class="px-3 py-1.5 font-mono text-xs">{{ $v->variant_sku ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-xs text-gray-600">{{ $v->article?->brand ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ rtrim(rtrim(number_format((float) $v->cost, 2), '0'), '.') ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ rtrim(rtrim(number_format((float) $v->fixed_price, 2), '0'), '.') ?: '—' }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ rtrim(rtrim(number_format((float) $v->min_margin, 2), '0'), '.') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="text-xs text-gray-400 mt-3 text-right">
                        {{ $bidVariantsAvailable->count() }} varianter · max 200 visas
                    </div>
                @endif
                <div class="text-xs text-gray-500 mt-3 italic">
                    Just nu visas alla BID-aktiva artiklar. Schema-stöd för "per-kund BID-behörighet" är inte implementerat ännu.
                </div>
            </div>

            {{-- Kundrabatter per varumärke --}}
            <div class="bg-white border rounded p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-700">Kundrabatter per varumärke</h3>
                    <span class="text-xs text-gray-400">
                        Rabatter som visas mot kund i prislistor
                        (<code class="bg-gray-100 px-1 rounded">article_supports · layer=customer</code>)
                    </span>
                </div>
                @if ($customerDiscountsByBrand->isEmpty())
                    <div class="border border-dashed rounded p-8 text-center text-sm text-gray-500">
                        Inga kundrabatter ligger aktiva på artiklar.
                    </div>
                @else
                    <div class="space-y-5">
                        @foreach ($customerDiscountsByBrand as $brandName => $rows)
                            <div>
                                <div class="flex items-baseline justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-gray-700">
                                        <a href="/admin/brands/{{ rawurlencode($brandName) }}?api_key={{ urlencode($apiKey) }}"
                                           class="text-blue-600 hover:underline">{{ $brandName }}</a>
                                    </h4>
                                    <span class="text-xs text-gray-500">{{ $rows->count() }} rabatter</span>
                                </div>
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-semibold">Artikel</th>
                                            <th class="px-3 py-1.5 text-left font-semibold">Typ</th>
                                            <th class="px-3 py-1.5 text-right font-semibold">Rabatt</th>
                                            <th class="px-3 py-1.5 text-left font-semibold">Period</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        @foreach ($rows as $r)
                                            @php
                                                $unit = $r->is_percentage ? '%' : ($r->currency ?: 'SEK');
                                                $period = '—';
                                                if ($r->date_from || $r->date_to) {
                                                    $period = ($r->date_from ?? '—') . ' → ' . ($r->date_to ?? '—');
                                                }
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-1 font-mono">
                                                    <a href="/admin/articles/{{ rawurlencode($r->article_number) }}?api_key={{ urlencode($apiKey) }}"
                                                       class="text-blue-600 hover:underline">{{ $r->article_number }}</a>
                                                    <span class="text-gray-500"> · {{ \Illuminate\Support\Str::limit($r->description, 35) }}</span>
                                                </td>
                                                <td class="px-3 py-1 text-gray-600">{{ ucfirst($r->customer_type) }}</td>
                                                <td class="px-3 py-1 text-right">
                                                    {{ rtrim(rtrim(number_format((float) $r->value, 2), '0'), '.') ?: '0' }}
                                                    <span class="text-gray-500">{{ $unit }}</span>
                                                </td>
                                                <td class="px-3 py-1 text-gray-600">{{ $period }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="text-xs text-gray-500 mt-3 italic">
                    Rabatter är artikel-scopade idag. Ett separat schema för rena brand- eller kategori-rabatter per kund är en senare utbyggnad.
                </div>
            </div>
            @break

        @case('logins')
            <div class="bg-white border rounded p-12 text-center">
                <div class="text-5xl mb-3">🔐</div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Web-inloggningar</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    Här listas de inloggningar (e-post + roll) som kunden har
                    till kundwebben. Ingen <code class="bg-gray-100 px-1 rounded">web_logins</code>-tabell
                    finns i pim-vendora ännu — mockad placeholder.
                </p>
            </div>
            @break

        @case('crm')
            @if ($crmStats)
                <div class="bg-white border rounded p-5 mb-4">
                    <h3 class="text-sm font-semibold uppercase text-gray-500 mb-3">Aggregerad försäljning (skickas till CRM)</h3>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 uppercase">12-månaders omsättning</div>
                            <div class="text-lg font-semibold text-gray-800">{{ number_format($crmStats['revenue_12m_sek'], 0, ',', ' ') }} kr</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase">30-dagars omsättning</div>
                            <div class="text-lg font-semibold text-gray-800">{{ number_format($crmStats['revenue_30d_sek'], 0, ',', ' ') }} kr</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Ordrar senaste 12 mån</div>
                            <div class="text-lg font-semibold text-gray-800">{{ number_format($crmStats['orders_12m'], 0, ',', ' ') }}</div>
                        </div>
                    </div>
                    @if (!empty($crmStats['top_brands']))
                        <div class="mt-4">
                            <div class="text-xs text-gray-500 uppercase mb-2">Topp 5 varumärken (12 mån)</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($crmStats['top_brands'] as $b)
                                    <span class="bg-gray-100 border rounded px-2 py-1 text-xs">
                                        <strong>{{ $b['brand'] }}</strong>
                                        <span class="text-gray-500">· {{ number_format($b['revenue_sek'], 0, ',', ' ') }} kr</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if ($crmUrl === null)
                <div class="bg-white border rounded p-12 text-center">
                    <div class="text-5xl mb-3">⚠️</div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">VAT-nummer saknas</h3>
                    <p class="text-sm text-gray-500 max-w-md mx-auto">
                        CRM-länken byggs med kundens <code class="bg-gray-100 px-1 rounded">vat_number</code>.
                        Kunden har inget VAT-nummer i databasen, så CRM-uppslaget kan inte göras.
                    </p>
                </div>
            @elseif ($crmIframe)
                <div class="bg-white border rounded overflow-hidden">
                    <div class="flex justify-between items-center px-4 py-2 bg-gray-50 border-b text-xs text-gray-500">
                        <span>Vendora CRM · VAT <code class="bg-white px-1 rounded border">{{ $customer->vat_number }}</code></span>
                        <a href="{{ $crmUrl }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                            Öppna i ny flik ↗
                        </a>
                    </div>
                    <iframe
                        src="{{ $crmUrl }}"
                        loading="lazy"
                        class="w-full block"
                        style="height: 800px; border: 0;"
                        title="Vendora CRM — {{ $customer->name }}">
                    </iframe>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Om iframen är tom blockerar CRM:en troligen embedding (X-Frame-Options / CSP).
                    Sätt då <code class="bg-gray-100 px-1 rounded">VENDORA_CRM_IFRAME=false</code> så
                    visas en "Öppna i ny flik"-knapp istället.
                </p>
            @else
                <div class="bg-white border rounded p-12 text-center">
                    <div class="text-5xl mb-3">🔗</div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Öppna kundkort i Vendora CRM</h3>
                    <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
                        Vendora CRM blockerar embedding — länken öppnas i en ny flik istället.
                    </p>
                    <a href="{{ $crmUrl }}" target="_blank" rel="noopener"
                       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded">
                        Öppna i Vendora CRM →
                    </a>
                    <div class="text-xs text-gray-400 mt-4 font-mono break-all">{{ $crmUrl }}</div>
                </div>
            @endif
            @break

        @case('contacts')
            <div class="bg-white border rounded p-12 text-center">
                <div class="text-5xl mb-3">👥</div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Kontakter</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    Här listas kundens kontaktpersoner (namn, roll, e-post, telefon).
                    Ingen <code class="bg-gray-100 px-1 rounded">customer_contacts</code>-tabell
                    finns i pim-vendora ännu — mockad placeholder.
                </p>
            </div>
            @break

        @default
            <div class="bg-white border rounded p-12 text-center">
                <div class="text-5xl mb-3">🚧</div>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">{{ ucfirst($activeTab) }}</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    This tab is not implemented in the pim-vendora demo —
                    it lives in the <code class="bg-gray-100 px-1 rounded">adm.vendora.se</code> admin SPA.
                </p>
            </div>
    @endswitch

    <div class="text-xs text-gray-500 mt-6">
        <strong>Note:</strong> Read-only demo. The real editable form lives in adm.vendora.se.
    </div>
</div>
@endsection
