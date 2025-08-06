@php
$completed = $completed ?? false;
@endphp

<div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
        <thead>
        <tr>
            <th>Order</th>
            <th>Date</th>
            <th class="text-end">Total</th>
            <th class="text-end">Status</th>
            @if(!$completed)
                <th class="text-end">Manage</th>
            @endif
        </tr>
        </thead>
        <body>
        @if($orders)
            @foreach($orders as $purchaseOrder)
                <tr>
                    <td>#{{ $purchaseOrder['id'] }}</td>
                    <td>{{ date('d M Y', strtotime($purchaseOrder['date'])) }}</td>
                    <td class="text-end">{{ $purchaseOrder['amount'] }} {{ $purchaseOrder['currency'] }}</td>
                    <td class="d-flex align-items-center justify-content-end">
                        @include('supplierPortal.partials.purchaseOrderStatus', ['purchaseOrder' => $purchaseOrder])
                    </td>
                    @if(!$completed)
                        <td class="text-end">
                            <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder['id']]) }}" class="btn btn-table btn-primary">Open <i class="bi bi-arrow-right"></i></a>
                        </td>
                    @endif
                </tr>
            @endforeach
        @endif
        </body>
    </table>
</div>
