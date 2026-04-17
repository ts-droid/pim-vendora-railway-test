@extends('layouts.pricing')

@section('title', $customer->customer_number . ' · Customer Database')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($customer->customer_number) . '</span> <span class="text-gray-400">·</span> <span>Customer Database</span>',
])

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
            'addresses' => 'Addresses',
            'orders'    => 'Orders',
            'invoices'  => 'Invoices',
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
