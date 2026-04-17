@extends('layouts.pricing')

@section('title', $supplier->number . ' · Supplier Database')

@section('content')
@include('admin._header', [
    'rightLabel' => '<span class="font-semibold">' . e($supplier->number) . '</span> <span class="text-gray-400">·</span> <span>Supplier Database</span>',
])
@include('admin._nav', ['activeNav' => 'suppliers', 'apiKey' => $apiKey])

<div class="max-w-6xl mx-auto p-6">

    <div class="flex justify-between items-center mb-4">
        <a href="javascript:history.back()" class="text-sm text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
            <span>←</span> Back
        </a>
    </div>

    @include('admin._tabs', [
        'tabs' => [
            'general'    => 'General',
            'contacts'   => 'Contacts',
            'logistics'  => 'Logistics',
            'purchasing' => 'Purchasing',
        ],
        'queryPrefix' => 'api_key=' . urlencode($apiKey) . '&',
    ])

    @switch($activeTab)

        @case('general')
            <div class="bg-white border rounded p-6">
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Number <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $supplier->number }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Org number</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $supplier->org_number ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">VAT number</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 font-mono">{{ $supplier->vat_number ?: '—' }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Name <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->name }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Brand name</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->brand_name ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Supplier type <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->type ?: '—' }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Is product supplier</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->is_supplier ? 'Yes' : 'No' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Payment terms <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->credit_terms ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Currency <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->currency ?: '—' }}</div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Limit with supplier</label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ (int) ($supplier->limit ?? 0) }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Document language <span class="text-red-500">*</span></label>
                        <div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->language ?: '—' }}</div>
                    </div>
                    <div></div>

                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Manufacturer information</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 min-h-[100px] whitespace-pre-wrap text-sm">{{ $supplier->manufacturer_information ?: '—' }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase font-semibold mb-1">EU Representative</label>
                        <div class="border rounded px-3 py-2 bg-gray-50 min-h-[100px] whitespace-pre-wrap text-sm">{{ $supplier->eu_representative ?: '—' }}</div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save</button>
                    <button disabled title="Mocked — lives in adm.vendora.se" class="bg-green-600 text-white text-sm px-4 py-1.5 rounded opacity-50 cursor-not-allowed">Save &amp; back</button>
                </div>

                <div class="text-xs text-gray-400 mt-2 text-right">
                    Last updated: {{ $supplier->updated_at ?? '—' }}
                </div>
            </div>
            @break

        @case('contacts')
            <div class="bg-white border rounded p-6">
                <h3 class="text-sm font-semibold mb-4 uppercase text-gray-500">Main contact</h3>
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Name</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->main_contact_name ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Email</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->main_contact_email ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Phone</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->main_contact_phone1 ?: '—' }}</div></div>
                </div>

                <h3 class="text-sm font-semibold mb-4 uppercase text-gray-500">Remit contact</h3>
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Name</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->remit_contact_name ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Email</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->remit_contact_email ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Phone</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->remit_contact_phone1 ?: '—' }}</div></div>
                </div>

                <h3 class="text-sm font-semibold mb-4 uppercase text-gray-500">Supplier contact</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Name</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->supplier_contact_name ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Email</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->supplier_contact_email ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Phone</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->supplier_contact_phone1 ?: '—' }}</div></div>
                </div>
            </div>
            @break

        @case('logistics')
            @php
                $fmt = function ($line, $city, $country) {
                    $parts = array_filter([$line, $city, $country]);
                    return $parts ? implode("\n", $parts) : '—';
                };
            @endphp
            <div class="bg-white border rounded p-6">
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Main address</label><div class="border rounded px-3 py-2 bg-gray-50 whitespace-pre-line">{{ $fmt($supplier->main_address_line, $supplier->main_address_city, $supplier->main_address_country) }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Supplier address</label><div class="border rounded px-3 py-2 bg-gray-50 whitespace-pre-line">{{ $fmt($supplier->supplier_address_line, $supplier->supplier_address_city, $supplier->supplier_address_country) }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Remit address</label><div class="border rounded px-3 py-2 bg-gray-50 whitespace-pre-line">{{ $fmt($supplier->remit_address_line, $supplier->remit_address_city, $supplier->remit_address_country) }}</div></div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">General delivery time</label><div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $supplier->general_delivery_time }} days</div></div>
                    <div class="col-span-2"><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Shipping instructions</label><div class="border rounded px-3 py-2 bg-gray-50 whitespace-pre-line">{{ $supplier->shipping_instructions ?: '—' }}</div></div>
                </div>
            </div>
            @break

        @case('purchasing')
            <div class="bg-white border rounded p-6">
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Purchase system</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->purchase_system ?: '—' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Order interval</label><div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $supplier->purchase_order_interval }} days</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Purchase AI</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->purchase_ai ? 'Yes' : 'No' }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Min value</label><div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $supplier->purchase_min_value }} {{ $supplier->currency }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Min quantity</label><div class="border rounded px-3 py-2 bg-gray-50">{{ (int) $supplier->purchase_min_quantity }}</div></div>
                    <div><label class="block text-xs text-gray-500 uppercase font-semibold mb-1">Purchase master box</label><div class="border rounded px-3 py-2 bg-gray-50">{{ $supplier->purchase_master_box ?: '—' }}</div></div>
                </div>
            </div>
            @break

    @endswitch

    <div class="text-xs text-gray-500 mt-6">
        <strong>Note:</strong> Read-only demo. The real editable form lives in adm.vendora.se.
    </div>
</div>
@endsection
