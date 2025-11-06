@php
$completed = $completed ?? false;
@endphp

<div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
        <thead>
        <tr>
            <th>Order</th>
            <th>Your order nr.</th>
            <th>State</th>
            <th>Date</th>
            <th class="text-end">Total</th>
            <th class="text-end">Not shipped</th>
            <th class="text-end">Shipping status</th>
            <th class="text-end">Status</th>
            @if(!$completed)
                <th class="text-end">Manage</th>
            @endif
        </tr>
        </thead>
        <body>
        @if($orders)
            @foreach($orders as $purchaseOrder)
                @php($emptyShipmentID = (new \App\Models\PurchaseOrder())->getEmptyShipment($purchaseOrder['id'])->id ?? 0)
                <tr>
                    <td>#{{ $purchaseOrder['id'] }} / {{ $purchaseOrder['order_number'] }}</td>
                    <td>{{ $purchaseOrder['supplier_order_number'] }}</td>
                    <td>{{ $purchaseOrder['published_at'] ? 'Confirmed' : 'Unconfirmed' }}</td>
                    <td>{{ date('d M Y', strtotime($purchaseOrder['date'])) }}</td>
                    <td class="text-end">{{ $purchaseOrder['amount'] }} {{ $purchaseOrder['currency'] }}</td>
                    <td class="text-end">{{ ($purchaseOrder['not_shipped_value'] ?? 0) }} {{ $purchaseOrder['currency'] }}</td>
                    <td class="text-end">{{ $purchaseOrder['shipping_status'] }}</td>
                    <td class="d-flex align-items-center justify-content-end">
                        @if($purchaseOrder['is_direct'])
                            <div class="d-inline me-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Direct Delivery">
                                <i class="bi bi-box2-fill"></i>
                                <i class="bi bi-arrow-right-short"></i>
                                <i class="bi bi-person-fill"></i>
                            </div>
                        @endif
                        @include('supplierPortal.partials.purchaseOrderStatus', ['purchaseOrder' => $purchaseOrder])
                    </td>
                    @if(!$completed)
                        <td class="text-end">
                            <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder['id'], 'shipment_id' => $emptyShipmentID]) }}" class="btn btn-table btn-primary">Open <i class="bi bi-arrow-right"></i></a>
                        </td>
                    @endif
                </tr>
            @endforeach
        @endif
        </body>
    </table>
</div>
