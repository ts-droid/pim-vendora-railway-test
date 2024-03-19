<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
        <tr>
            <th></th>
            <th>Order Number</th>
            <th>Order Date</th>
            <th>Last view</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @if($orders)
            @foreach($orders as $purchaseOrder)
                <tr>
                    <td style="width: 1px;">
                        <div class="row-status {{ $purchaseOrder->getColorStatus() }}"></div>
                    </td>
                    <td>{{ $purchaseOrder->order_number }}</td>
                    <td>{{ $purchaseOrder->date }}</td>
                    <td>{!! $purchaseOrder->viewed_at ? date('Y-m-d H:i', strtotime($purchaseOrder->viewed_at)) : '<i>Never</i>' !!}</td>
                    <td class="text-end">
                        <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>
</div>
