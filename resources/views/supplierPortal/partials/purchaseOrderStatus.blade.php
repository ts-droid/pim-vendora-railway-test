<div class="order-status-bar {{ (isset($small) && $small) ? 'order-status-bar__small' : '' }}">
    <div class="{{ $purchaseOrder['status_confirmed_by_supplier'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Confirmed order"></div>
    <div class="{{ $purchaseOrder['status_shipping_details'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Shipping dates provided"></div>
    <div class="{{ $purchaseOrder['status_tracking_number'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Tracking number provided"></div>
    <div class="{{ $purchaseOrder['status_invoice_uploaded'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Invoice uploaded"></div>
    <div class="{{ $purchaseOrder['status_received'] ? 'green' : 'red' }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Shipment received by Vendora"></div>
</div>
