<div class="order-status-bar {{ (isset($small) && $small) ? 'order-status-bar__small' : '' }}">
    <div class="{{ $purchaseOrder['status_confirmed_by_supplier'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Confirmed by supplier"></div>
    <div class="{{ $purchaseOrder['status_shipping_details'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Supplier provided shipping details"></div>
    <div class="{{ $purchaseOrder['status_tracking_number'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Supplier provided tracking number"></div>
    <div class="{{ $purchaseOrder['status_invoice_uploaded'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Supplier uploaded invoice"></div>
    <div class="{{ $purchaseOrder['status_received'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Shipment received"></div>
</div>
