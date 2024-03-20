<div class="column-table">
    <div class="row fw-bold border-bottom py-2">
        <div class="col col-4">Order Number</div>
        <div class="col col-2">Order Date</div>
        <div class="col col-2">Last View</div>
        <div class="col col-2">Status</div>
        <div class="col col-2"></div>
    </div>

    @if($orders)
        @foreach($orders as $purchaseOrder)
            <div class="row border-bottom">
                <div class="col col-4 d-flex align-items-center py-1">
                    <div class="row-status {{ $colorStatus }} me-2" data-bs-toggle="tooltip" data-bs-placement="right" title="{{ $colorText }}"></div>
                    <div>{{ $purchaseOrder->order_number }}</div>
                </div>
                <div class="col col-2 d-flex align-items-center py-1">{{ $purchaseOrder->date }}</div>
                <div class="col col-2 d-flex align-items-center py-1">{!! $purchaseOrder->viewed_at ? date('Y-m-d H:i', strtotime($purchaseOrder->viewed_at)) : '<i>Never</i>' !!}</div>
                <div class="col col-2 d-flex align-items-center py-1">
                    <i class="bi {{ $purchaseOrder->missingETA() ? 'bi-check-circle text-muted' : 'bi-check-circle-fill text-success' }} me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Shipping dates"></i>
                    <i class="bi {{ $purchaseOrder->missingTrackingNumbers() ? 'bi-check-circle text-muted' : 'bi-check-circle-fill text-success' }} me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Tracking numbers"></i>
                    <i class="bi {{ $purchaseOrder->isFullyInvoiced() ? 'bi-check-circle-fill text-success' : 'bi-check-circle text-muted' }} me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Upload invoice"></i>
                </div>
                <div class="col col-md-2 d-flex align-items-center justify-content-end py-1">
                    <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-sm btn-primary">View</a>
                </div>
            </div>
        @endforeach
    @endif
</div>
