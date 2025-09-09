@php
$portalStatus = $purchaseOrder->getPortalStatus();

$priceEditable = $portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED;
$quantityEditable = $portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED;
@endphp

@extends('supplierPortal.layout')

@section('content')
    <div class="container-fluid">

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="h4 mb-2">
                    <b>Vendora Order no:</b> <span id="order-number">{{ $purchaseOrder->id }}</span> <span class="copy-btn" onclick="copyToClipboard('#order-number')"><i class="bi bi-copy"></i></span>
                </div>
                <div><b>Order date:</b> {{ $purchaseOrder->date }}</div>
            </div>
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="color-description-preview me-2" style="background-color: #ffd970;"></div>
                    <div>= Editable fields</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 d-flex align-items-center mb-3">
                <label class="form-label fw-bold sm mb-1 me-2">Your order number:</label>
                <input type="text" class="form-control form-control-sm" name="supplier_order_number" value="{{ $purchaseOrder['supplier_order_number'] }}" style="width: 250px;">
            </div>
            <div class="col-md-6 mb-4 text-end">
                @if($portalStatus != \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED && (!$purchaseOrder->status_shipping_details || !$purchaseOrder->status_tracking_number))
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createShipmentModal">Create Shipment</button>
                @endif

                @if($portalStatus != \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED && !$purchaseOrder->isFullyInvoiced())
                    <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#invoiceModal">Upload Invoice</button>
                @endif
            </div>
        </div>

        @if($purchaseOrder->is_direct)
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning p-2" role="alert">
                        <div class="fw-bold mb-2">This is a direct delivery that you should send directly to the below address:</div>
                        <div>
                            {{ $purchaseOrder->directOrder->shippingAddress->full_name ?? '' }}<br>
                            {{ $purchaseOrder->directOrder->shippingAddress->street_line_1 ?? '' }}<br>
                            @if($purchaseOrder->directOrder->shippingAddress->street_line_2 ?? null)
                                {{ $purchaseOrder->directOrder->shippingAddress->street_line_2 ?? '' }}<br>
                            @endif
                            {{ $purchaseOrder->directOrder->shippingAddress->postal_code ?? '' }} {{ $purchaseOrder->directOrder->shippingAddress->city ?? '' }}<br>
                            @if($purchaseOrder->directOrder->shippingAddress->country_code ?? null)
                                {{ get_country_name($purchaseOrder->directOrder->shippingAddress->country_code) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Shipments</h5>

                        @if($shipments && $shipments->count() > 0)
                            <div class="table-responsive">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped po-view-table mb-0">
                                        <thead>
                                        <tr>
                                            <th>Shipment</th>
                                            <th class="text-end">Items</th>
                                            <th class="text-end">Tracking number</th>
                                            <th class="text-end">Created</th>
                                            <th class="text-end"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($shipments as $shipment)
                                            <tr>
                                                <td>#{{ $shipment->id }}</td>
                                                <td class="text-end">{{ $shipment->lines->count() }}</td>
                                                <td class="text-end">{{ $shipment->tracking_number }}</td>
                                                <td class="text-end">{{ date('d M Y', strtotime($shipment->created_at)) }}</td>
                                                <td class="text-end">
                                                    <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'shipment_id' => $shipment->id]) }}">Shipping instructions</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-muted">No shipments</div>
                        @endif

                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Invoices</h5>

                        @if($invoices && $invoices->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-striped po-view-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th class="text-end">Lines</th>
                                        <th class="text-end">Amount</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($invoices as $invoice)
                                        <tr>
                                            <td>
                                                <a href="{{ \App\Http\Controllers\DoSpacesController::getURL($invoice->filename) }}" target="_blank">{{ $invoice->client_filename ?: $invoice->filename }}</a>
                                            </td>
                                            <td class="text-end">{{ $invoice->lines->count() }}</td>
                                            <td class="text-end">{{ number_format($invoice->lines->sum(fn($line) => $line->unit_cost * $line->quantity), 2, '.', '') }} {{ $purchaseOrder->currency }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('supplierPortal.purchaseOrders.order.deleteInvoice', ['purchaseOrder' => $purchaseOrder->id, 'supplierInvoice' => $invoice->id]) }}"
                                                    class="delete-invoice"
                                                    onclick="return confirm('Are you sure you want to remove this invoice?');">
                                                    <i class="bi bi-x-circle-fill"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted">No invoices</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Order Content</h5>
                            @include('supplierPortal.partials.purchaseOrderStatus', ['purchaseOrder' => $purchaseOrder->toArray()])
                        </div>

                        <div class="table-responsive">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped po-view-table">
                                    <thead>
                                    <tr>
                                        <th colspan="2">Article number</th>
                                        <th>Description</th>
                                        <th class="text-center">Shipped</th>
                                        <th class="text-center">Invoiced</th>
                                        <th class="text-end">Unit price</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Shipping date</th>
                                        <th class="text-end">Tracking number</th>
                                        @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                            <th class="text-end">Status</th>
                                        @endif
                                        @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                            <th class="text-end"></th>
                                        @endif
                                    </tr>
                                    </thead>
                                    <tbody id="order-table-body">

                                    @php($total = 0)
                                    @php($totalQuantity = 0)

                                    @foreach($purchaseOrder->lines as $line)

                                        @php($total += ($line->quantity * $line->unit_cost))
                                        @php($totalQuantity += $line->quantity)

                                        <tr class="js-item-row {{ ($line->is_shipped && $line->invoice_id && $line->tracking_number) ? 'opacity-50' : '' }}" data-id="{{ $line->id }}">
                                            <td class="no-wrap" style="width: 1px;"><span id="article-number-{{ $line->id }}">{{ $line->article_number }}</span></td>
                                            <td>
                                                <span class="copy-btn" onclick="copyToClipboard('#article-number-{{ $line->id }}')"><i class="bi bi-copy"></i></span>
                                            </td>
                                            <td>{{ $line->description }}</td>
                                            <td class="text-center" style="width: 90px;">
                                                {!! ($line->is_shipped ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>') !!}
                                            </td>
                                            <td class="text-center" style="width: 90px;">
                                                {!! ($line->invoice_id ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>') !!}
                                            </td>
                                            <td style="width: 150px;">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm text-end js-unit-cost" name="unit_cost_{{ $line->id }}" value="{{ $line->unit_cost }}" {{ $priceEditable ? '' : 'readonly' }}>
                                                    <span class="input-group-text">{{ $purchaseOrder->currency }}</span>
                                                </div>
                                            </td>
                                            <td style="width: 100px;">
                                                <input type="text" class="form-control form-control-sm text-end js-quantity" name="quantity_{{ $line->id }}" value="{{ $line->quantity }}"
                                                       data-default="{{ $line->quantity }}" {{ $quantityEditable ? '' : 'readonly' }}>
                                            </td>
                                            <td style="width: 100px;" class="text-end no-wrap">
                                                <span class="js-price">{{ number_format(($line->quantity * $line->unit_cost), 2, '.', ' ') }}</span> {{ $purchaseOrder->currency }}
                                            </td>
                                            <td style="width: 150px;">
                                                <input type="text" class="form-control form-control-sm text-end js-datepicker" name="shipping_date_{{ $line->id }}" value="{{ $line->getShippingDate() }}" {{ $line->is_completed ? 'readonly' : '' }}>
                                            </td>
                                            <td style="width: 250px;">
                                                <input type="text" class="form-control form-control-sm text-end" name="tracking_number_{{ $line->id }}" value="{{ $line->tracking_number }}" placeholder="ex. 12345678901" {{ $line->is_completed ? 'readonly' : '' }}>
                                            </td>
                                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                                <td style="width: 150px;">
                                                    <select class="form-select form-select-sm" name="status_{{ $line->id }}">
                                                        <option value="">-----</option>
                                                        <option value="confirm">Confirm</option>
                                                        <option value="eol">End of Life</option>
                                                    </select>
                                                </td>
                                            @else
                                                <td></td>
                                            @endif

                                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                                <td class="text-end">
                                                    @if(!$line->is_shipped && !$line->is_completed && $line->quantity > 1)
                                                        <span class="text-primary cursor-pointer js-split-line" data-line="{{ $line->id }}" data-qty="{{ $line->quantity }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Split row">
                                                            <i class="bi bi-scissors"></i>
                                                        </span>
                                                    @endif
                                                        @if(!$line->is_shipped && !$line->is_completed)
                                                            <span class="text-danger cursor-pointer js-cancel-row ms-2" data-line="{{ $line->id }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancel row">
                                                                <i class="bi bi-x-circle-fill"></i>
                                                            </span>
                                                        @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach

                                    @foreach($purchaseOrder->canceledLines as $line)
                                        <tr class="bg-cancelled">
                                            <td class="no-wrap" style="width: 1px;">{{ $line->article_number }}</td>
                                            <td></td>
                                            <td>{{ $line->description }}</td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end small">{{ $line->unit_price }} {{ $purchaseOrder->currency }}</td>
                                            <td class="text-end small">{{ $line->quantity }}</td>
                                            <td class="text-end small">{{ round($line->unit_price * $line->quantity, 2) }} {{ $purchaseOrder->currency }}</td>
                                            <td></td>
                                            <td></td>
                                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                                <td></td>
                                            @endif
                                        </tr>
                                    @endforeach

                                    </tbody>
                                    <tfoot>
                                    <tr>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-end fw-bold js-total-quantity">{{ number_format($totalQuantity, 0, '.', '') }}</td>
                                        <td class="text-end fw-bold no-wrap js-total-price">{{ number_format($total, 2, '.', ' ') }} {{ $purchaseOrder->currency }}</td>
                                        <td></td>
                                        @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                            <td></td>
                                        @endif
                                        @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                            <td></td>
                                        @endif
                                        @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                            <td></td>
                                        @endif
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 text-end">
                @if($portalStatus != \App\Models\PurchaseOrder::PORTAL_STATUS_CLOSED)
                    <button class="btn btn-primary js-confirm-button" onclick="confirmOrder()">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Save
                    </button>
                @endif
            </div>
        </div>

    </div>


    <!-- Quantity Reduction Modal -->
    <div class="modal fade" id="quantityReductionModal" tabindex="-1" aria-labelledby="quantityReductionModalLabel" aria-hidden="true">

        <div class="d-none js-quantity-reduction-id"></div>

        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="h5 fw-normal text-center mb-3">
                        Do you want to move <span class="js-quantity-reduction-amount"></span> pcs of this item to a separate order row?
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="d-grid">
                                <button class="btn btn-danger" onclick="removeReduction()">No, delete</button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-grid">
                                <button class="btn btn-success" onclick="moveReduction()">Yes, move</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Split line Modal -->
    <div class="modal fade" id="splitLineModal" tabindex="-1" aria-labelledby="splitLineModalLabel" aria-hidden="true">

        <div class="d-none js-split-line-id"></div>

        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center">

                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <div>Move</div>
                        <input type="text" class="form-control form-control-sm text-center mx-2 js-split-new-quantity" style="width: 80px;">
                        <div> of <span class="fw-bold js-split-current-quantityt">0</span> pcs to a new line.</div>
                    </div>

                    <div class="row">
                        <div class="col-6 d-grid">
                            <button class="btn btn-secondary js-split-cancel">Cancel</button>
                        </div>
                        <div class="col-6 d-grid">
                            <button class="btn btn-success js-split-submit">Split</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- Create Shipment Modal -->
    <div class="modal fade" id="createShipmentModal" tabindex="-1" aria-labelledby="createShipmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('supplierPortal.purchaseOrders.order.createShipment', ['purchaseOrder' => $purchaseOrder->id]) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="createShipmentModalLabel">Create Shipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-5">
                            <label class="form-label fw-bold small">1. Upload packing slip</label>
                            <input type="file" class="form-control" name="receipt" id="receipt-file" accept="application/pdf" required>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold small">2. Enter tracking number</label>
                            <input type="text" class="form-control" name="tracking_number" required>
                        </div>

                        <label class="form-label fw-bold small">3. Select order lines</label>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 1px;">
                                        @if(!$purchaseOrder->is_direct)
                                            <input type="checkbox" class="form-check-input js-all-shipment-rows">
                                        @endif
                                    </th>
                                    <th>Article</th>
                                    <th>Quantity</th>
                                </tr>
                                @foreach($purchaseOrder->lines as $line)
                                    @php($isShippable = (!$line->is_shipped && !$line->is_completed))

                                    <tr class="{{ $isShippable ? '' : 'opacity-50' }}">
                                        <td>
                                            @if($isShippable)
                                                <input type="checkbox"
                                                       class="form-check-input js-shipment-row"
                                                       name="purchase_order_lines[]"
                                                       value="{{ $line->id }}"
                                                       onclick="{{ $purchaseOrder->is_direct ? 'return false;' : '' }}"
                                                        {{ $purchaseOrder->is_direct ? 'checked' : '' }}>
                                            @endif
                                        </td>
                                        <td>{{ $line->article_number }}</td>
                                        <td>{{ $line->quantity }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Create</button>
                    </div>

                </form>
            </div>
        </div>
    </div>


    <!-- Upload Invoice Modal -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('supplierPortal.purchaseOrders.order.uploadInvoice', ['purchaseOrder' => $purchaseOrder->id]) }}" class="js-invoice-form" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="invoiceModalLabel">Upload Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <p>
                            Upload an invoice and mark the order rows associated with the invoice.
                        </p>

                        <div class="mb-3">
                            <input type="file" class="form-control" name="invoice" id="invoice-file" accept="application/pdf">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 1px;">
                                        @if(!$purchaseOrder->is_direct)
                                            <input type="checkbox" class="form-check-input js-all-invoice-rows">
                                        @endif
                                    </th>
                                    <th>Article</th>
                                    <th>Quantity</th>
                                </tr>
                                @foreach($purchaseOrder->lines as $line)
                                    <tr class="{{ ($line->invoice_id || !$line->is_shipped) ? 'opacity-50' : '' }}">
                                        <td>
                                            @if(!$line->invoice_id && $line->is_shipped)
                                                <input type="checkbox"
                                                       class="form-check-input js-invoice-row"
                                                       name="purchase_order_lines[]"
                                                       value="{{ $line->id }}"
                                                       onclick="{{ $purchaseOrder->is_direct ? 'return false;' : '' }}"
                                                        {{ $purchaseOrder->is_direct ? 'checked' : '' }}>
                                            @endif
                                        </td>
                                        <td>{{ $line->article_number }}</td>
                                        <td>{{ $line->quantity }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    @if($openShipment)
        @php($qrData = [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_shipment_id' => $openShipment->id ?? 0,
        ])

        @php($qrMetaData = [
            'Vendora order.nr' => $purchaseOrder->id ?: '--',
            'Visma order.nr' => $purchaseOrder->order_number ?: '--',
            'Shipment.nr' => $openShipment->id ?: '--',
            'Supplier' => $purchaseOrder->supplier->name ?: '--',
            'Tracking number' => $openShipment->tracking_number ?: '--',
        ])

        <div class="shipment-instructions">
            <div class="shipment-instructions__content">
                <h4 class="mb-4"><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Important instructions</h4>

                @if($selectedShippingInstructions)
                    <div class="alert alert-warning small mb-4" role="alert">
                        {{ $selectedShippingInstructions }}
                    </div>
                @endif

                @if($purchaseOrder->is_direct)
                    <div class="mb-3">
                        <div class="fw-bold mb-2">This is a direct delivery that you should send directly to the below address:</div>
                        <div>
                            {{ $purchaseOrder->directOrder->shippingAddress->full_name ?? '' }}<br>
                            {{ $purchaseOrder->directOrder->shippingAddress->street_line_1 ?? '' }}<br>
                            @if($purchaseOrder->directOrder->shippingAddress->street_line_2 ?? null)
                                {{ $purchaseOrder->directOrder->shippingAddress->street_line_2 ?? '' }}<br>
                            @endif
                            {{ $purchaseOrder->directOrder->shippingAddress->postal_code ?? '' }} {{ $purchaseOrder->directOrder->shippingAddress->city ?? '' }}<br>
                            @if($purchaseOrder->directOrder->shippingAddress->country_code ?? null)
                                {{ get_country_name($purchaseOrder->directOrder->shippingAddress->country_code) }}
                            @endif
                        </div>
                    </div>
                @else
                    <div class="mb-3">
                        You <b>MUST</b> place the below QR-code on the package when shipping the order. This is required for us to be able to match the shipment with the order.<br>
                        <br>
                        If this is not done, a manual handling fee will be charged to you.
                    </div>

                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-6 d-grid">
                                <a href="{{ route('supplierPortal.qrCode.print', ['data' => json_encode($qrData), 'meta_data' => $qrMetaData]) }}" class="btn btn-sm btn-primary" target="_blank">Print QR-code</a>
                            </div>
                            <div class="col-md-6 d-grid">
                                <a href="{{ route('supplierPortal.qrCode.download', ['data' => json_encode($qrData), 'meta_data' => $qrMetaData]) }}" class="btn btn-sm btn-primary" target="_blank">Download QR-code</a>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="d-grid">
                    <a class="btn btn-success" href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id]) }}"><i class="bi bi-check-lg"></i> Complete</a>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script')
    <script>
        function initDatepicker()
        {
            $('.js-datepicker').each(function() {
                if ($(this).is('[readonly]')) {
                    // Do not active for readonly inputs
                    return;
                }

                $(this).datepicker({
                    minDate: 0,
                    firstDay: 1,
                    dateFormat: 'yy-mm-dd'
                });
            });
        }

        $(function() {
            initDatepicker();

            $(document).on('click', '.js-cancel-row', function() {
                if (!confirm('Are you sure you want to cancel this row? This action cannot be undone.')) {
                    return false;
                }

                const lineID = $(this).data('line');

                showLoader();

                $.post('{{ route('purchaseOrders.cancelRow', ['purchaseOrder' => $purchaseOrder->id, 'api_key' => get_internal_api_key()]) }}', {
                    line_id: lineID
                }, function(response) {
                    if (!response.success) {
                        console.log(response);
                        alert(response.error_message || 'Something went wrong when trying to cancel the row. Please try again.');
                        hideLoader();
                    } else {
                        window.location.reload();
                    }
                });
            });

            $(document).on('click', '.js-split-line', function() {
                const lineID = $(this).data('line');
                const currentQty = $(this).data('qty');

                $('.js-split-line-id').text(lineID);
                $('.js-split-current-quantityt').text(currentQty);

                $('#splitLineModal').modal('show');
            });

            $(document).on('click', '.js-split-cancel', function() {
                $('#splitLineModal').modal('hide');
            });

            $(document).on('click', '.js-split-submit', function() {
                const $button = $(this);
                $button.prop('disabled', true);

                const lineID = parseInt($('.js-split-line-id').text());
                const qty = parseInt($('.js-split-new-quantity').val());
                const currentQty = parseInt($('.js-split-current-quantityt').text());
                const leftoverQty = currentQty - qty;

                if (isNaN(qty) || qty <= 0) {
                    alert('Please enter a valid quantity to split.');
                    $button.prop('disabled', false);
                    return;
                }

                if (qty >= currentQty) {
                    alert('You cannot split more than the current quantity.');
                    $button.prop('disabled', false);
                    return;
                }

                let currencyCode = '{{ $purchaseOrder->currency }}';
                let priceEditable = {{ $priceEditable ? 'true' : 'false' }};
                let quantityEditable = {{ $quantityEditable ? 'true' : 'false' }};
                let portalStatus = '{{ $portalStatus }}';

                $.post('{{ route('purchaseOrders.copyLine', ['purchaseOrder' => $purchaseOrder->id, 'api_key' => get_internal_api_key()]) }}', {
                    line_id: lineID,
                    quantity: qty
                }, function(response) {
                    if (!response.success) {
                        console.log(response);
                        alert(response.error_message || 'Something went wrong when splitting the row. Please try again.');
                        $button.prop('disabled', false);
                    } else {
                        let newLine = response.data;

                        let rowColumns = '<td class="no-warp"><span id="article-number-' + newLine.id + '">' + newLine.article_number + '</span></td>' +
                            '<td>' +
                            '<span class="copy-btn" onclick="copyToClipboard(\'#article-number-' + newLine.id + '\')"><i class="bi bi-copy"></i></span>' +
                            '</td>' +
                            '<td>' + newLine.description + '</td>' +
                            '<td class="text-center" style="width: 90px;">' +
                                '<i class="bi bi-x-circle-fill text-danger"></i>' +
                            '</td>' +
                            '<td class="text-center" style="width: 90px;">' +
                                '<i class="bi bi-x-circle-fill text-danger"></i>' +
                            '</td>' +
                            '<td style="width: 150px;">' +
                                '<div class="input-group input-group-sm">' +
                                    '<input type="text" class="form-control form-control-sm text-end js-unit-cost" name="unit_cost_' + newLine.id + '" value="' + newLine.unit_cost + '" ' + (priceEditable ? '' : 'readonly') + '>' +
                                    '<span class="input-group-text">' + currencyCode + '</span>' +
                                '</div>' +
                            '</td>' +
                            '<td style="width: 100px">' +
                                '<input type="text" class="form-control form-control-sm text-end js-quantity" name="quantity_' + newLine.id + '" value="' + newLine.quantity + '" data-default="' + newLine.quantity + '" ' + (quantityEditable ? '' : 'readonly') + '>' +
                            '</td>' +
                            '<td style="width: 100px;" class="text-end no-wrap">' +
                                '<span class="js-price">' + formatCurrency(newLine.quantity * newLine.unit_cost) + '</span> ' + currencyCode +
                            '</td>' +
                            '<td>' +
                                '<input type="text" class="form-control form-control-sm text-end js-datepicker" name="shipping_date_' + newLine.id + '" value="">' +
                            '</td>';

                        if (portalStatus === '{{ \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN }}') {
                            rowColumns += '<td style="width: 250px;"></td>';

                            rowColumns += '<td>' +
                                                '<span class="link js-split-line" data-line="' + newLine.id + '" data-qty="' + newLine.quantity + '">Split</span>' +
                                            '</td>';
                        }

                        $('#order-table-body').append(
                            '<tr class="js-item-row" data-id="' + newLine.id + '">' +
                            rowColumns +
                            '</tr>'
                        );

                        $('#splitLineModal').modal('hide');
                        $button.prop('disabled', false);

                        updateTotal();
                    }
                });


            });

            $(document).on('change', '.js-unit-cost, .js-quantity', function() {
                let $row = $(this).closest('.js-item-row');

                let unitCost = $row.find('.js-unit-cost').val();
                unitCost = unitCost.replace(',', '.');
                $row.find('.js-unit-cost').val(unitCost);

                let quantity = $row.find('.js-quantity').val();
                quantity = parseInt(quantity);
                $row.find('.js-quantity').val(quantity);

                let total = unitCost * quantity;
                $row.find('.js-price').text(formatCurrency(total));

                updateTotal();
            });

            $(document).on('change', '.js-all-invoice-rows', function() {
                let checked = $(this).prop('checked');
                $('.js-invoice-row').prop('checked', checked);
            });

            $(document).on('change', '.js-all-shipment-rows', function() {
                let checked = $(this).prop('checked');
                $('.js-shipment-row').prop('checked', checked);
            });

            $(document).on('submit', '.js-invoice-form', function() {

                // Make sure an invoice is uploaded
                if ($('#invoice-file').val() === '') {
                    alert('Please upload an invoice.');
                    return false;
                }

                // Make sure at least one row is selected
                if ($('.js-invoice-row:checked').length === 0) {
                    alert('Please select at least one row to associate with the invoice.');
                    return false;
                }

                return true;
            });

            $(document).on('change', '.js-quantity', function() {
                let $row = $(this).closest('.js-item-row');
                let $modal = $('#quantityReductionModal');

                let rowID = $row.data('id');

                let quantity = parseInt($(this).val());
                let defaultQuantity = parseInt($(this).data('default'));

                if (quantity >= defaultQuantity) {
                    return;
                }

                let reduction = defaultQuantity - quantity;

                $('.js-quantity-reduction-amount').html(reduction);
                $('.js-quantity-reduction-id').html(rowID);

                $modal.modal({
                    backdrop: 'static',
                    keyboard: false
                });

                $modal.modal('show');
            });
        });

        function updateTotal()
        {
            let total = 0;
            let totalQuantity = 0;

            $('.js-price').each(function() {
                total += currencyToFloat($(this).text());
            });

            $('.js-quantity').each(function() {
                totalQuantity += parseInt($(this).val());
            });

            $('.js-total-price').text(formatCurrency(total));
            $('.js-total-quantity').text(totalQuantity.toFixed(0));
        }

        function confirmOrder()
        {
            // Start loading animation
            let $button = $('.js-confirm-button');
            loadButton($button);

            // Collect post data
            let postData = {
                supplier_order_number: $('input[name="supplier_order_number"]').val(),
                items: []
            };

            $('.js-item-row').each(function() {
                let id = $(this).data('id');
                let unitCost = $(this).find('input[name="unit_cost_' + id + '"]').val();
                let quantity = $(this).find('input[name="quantity_' + id + '"]').val();
                let shippingDate = $(this).find('input[name="shipping_date_' + id + '"]').val();
                let trackingNumber = $(this).find('input[name="tracking_number_' + id + '"]').val();
                let status = $(this).find('select[name="status_' + id + '"]').val();

                postData.items.push({
                    id: id,
                    unit_cost: unitCost,
                    quantity: quantity,
                    shipping_date: shippingDate,
                    tracking_number: trackingNumber,
                    status: status
                });
            });

            // Validate the rows
            for (let i = 0;i < postData.items.length;i++) {
                let item = postData.items[i];

                // Validate status
                if (item.status === '') {
                    resumeButton($button);
                    alert('Please select a status for all items.');
                    return;
                }

                // Validate shipping date
                if (item.status === 'confirm' && item.shipping_date === '') {
                    resumeButton($button);
                    alert('Please enter a shipping date for all confirmed items.');
                    return;
                }

                // Validate quantity
                if (item.quantity <= 0) {
                    resumeButton($button);
                    alert('Quantity must be greater than 0.');
                    return;
                }

                // Validate unit cost
                if (item.unit_cost <= 0) {
                    resumeButton($button);
                    alert('Unit cost must be greater than 0.');
                    return;
                }
            }

            // Post data
            fetch('{{ route('supplierPortal.purchaseOrders.order.post', ['purchaseOrder' => $purchaseOrder->id]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(postData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const shipmentID = data?.shipment_id ?? null;

                        if (shipmentID) {
                            window.location.href = '{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id]) }}?shipment_id=' + shipmentID;
                        } else {
                            window.location.reload();
                        }
                    }
                    else {
                        alert(data.message);
                        resumeButton($button);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                    resumeButton($button);
                });
        }

        function loadButton($button)
        {
            $button.prop('disabled', true);
            $button.find('.spinner-border').removeClass('d-none');
        }

        function resumeButton($button)
        {
            $button.prop('disabled', false);
            $button.find('.spinner-border').addClass('d-none');
        }

        function removeReduction()
        {
            let rowID = $('.js-quantity-reduction-id').html();

            // Update the default quantity to the new value
            let $quantityInput = $('input[name="quantity_' + rowID + '"]');
            let newDefaultQuantity = parseInt($quantityInput.val());

            $quantityInput.data('default', newDefaultQuantity);

            // Close the modal
            $('#quantityReductionModal').modal('hide');
        }

        function moveReduction()
        {
            let rowID = parseInt($('.js-quantity-reduction-id').html());
            let newQuantity = parseInt($('.js-quantity-reduction-amount').html());

            let currencyCode = '{{ $purchaseOrder->currency }}';
            let priceEditable = {{ $priceEditable ? 'true' : 'false' }};
            let portalStatus = '{{ $portalStatus }}';

            // Copy the row
            $.post('{{ route('purchaseOrders.copyLine', ['purchaseOrder' => $purchaseOrder->id, 'api_key' => get_internal_api_key()]) }}', {
                line_id: rowID,
                quantity: newQuantity
            }, function(response) {
                if (!response.success) {
                    console.log(response);
                    alert('Something went wrong when copying the row. Please try again.');
                    return;
                }
                else {
                    let newLine = response.data;

                    let rowColumns = '<td class="no-warp"><span id="article-number-' + newLine.id + '">' + newLine.article_number + '</span></td>' +
                        '<td>' +
                        '<span class="copy-btn" onclick="copyToClipboard(\'#article-number-' + newLine.id + '\')"><i class="bi bi-copy"></i></span>' +
                        '</td>' +
                        '<td>' + newLine.description + '</td>' +
                        '<td class="text-center" style="width: 90px;">' +
                            '<i class="bi bi-x-circle-fill text-danger"></i>' +
                        '</td>' +
                        '<td class="text-center" style="width: 90px;">' +
                            '<i class="bi bi-x-circle-fill text-danger"></i>' +
                        '</td>' +
                        '<td style="width: 150px;">' +
                            '<div class="input-group input-group-sm">' +
                                '<input type="text" class="form-control form-control-sm text-end js-unit-cost" name="unit_cost_' + newLine.id + '" value="' + newLine.unit_cost + '" ' + (priceEditable ? '' : 'readonly') + '>' +
                                '<span class="input-group-text">' + currencyCode + '</span>' +
                            '</div>' +
                        '</td>' +
                        '<td style="width: 100px">' +
                            '<input type="text" class="form-control form-control-sm text-end js-quantity" name="quantity_' + newLine.id + '" value="' + newLine.quantity + '" data-default="' + newLine.quantity + '">' +
                        '</td>' +
                        '<td style="width: 100px;" class="text-end no-wrap">' +
                            '<span class="js-price">' + formatCurrency(newLine.quantity * newLine.unit_cost) + '</span> ' + currencyCode +
                        '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm text-end js-datepicker" name="shipping_date_' + newLine.id + '" value="">' +
                        '</td>';

                    rowColumns += '<td style="width: 250px;">' +
                        '<input type="text" class="form-control form-control-sm text-end" name="tracking_number_' + newLine.id + '" value="' + newLine.tracking_number + '" placeholder="ex. 12345678901">' +
                        '</td>';

                    if (portalStatus === '{{ \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED }}') {
                        rowColumns += '<td style="width: 150px;">' +
                            ' <select class="form-select form-select-sm" name="status_' + newLine.id + '">' +
                            '<option value="">-----</option>' +
                            '<option value="confirm">Confirm</option>' +
                            '<option value="decline">Decline</option>' +
                            '<option value="eol">End of Life</option>' +
                            '</select>' +
                            '</td>';
                    }

                    if (portalStatus === '{{ \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN }}') {
                        rowColumns += '<td></td>';
                    }

                    // Add new row
                    $('#order-table-body').append(
                        '<tr class="js-item-row" data-id="' + newLine.id + '">' +
                        rowColumns +
                        '</tr>'
                    );

                    // Close the modal
                    $('#quantityReductionModal').modal('hide');

                    // Recalculate the total
                    updateTotal();

                    // Re-initiate datepicker
                    initDatepicker();
                }
            });
        }
    </script>
@endsection
