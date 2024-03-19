@php
$portalStatus = $purchaseOrder->getPortalStatus();

$priceEditable = $portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED;
$quantityEditable = $portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED;
@endphp

@extends('supplierPortal.layout')

@section('content')
    <div class="container-fluid">

        <div class="row mb-4">
            <div class="col-md-12">
                <div><b>Order no:</b> {{ $purchaseOrder->order_number }}</div>
                <div><b>Order date:</b> {{ $purchaseOrder->date }}</div>
                <div><b>Status:</b> {{ $portalStatus }}</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th>Article number</th>
                            <th>Description</th>
                            <th class="text-end">Unit price</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Shipping date</th>
                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                <th class="text-end">Tracking number</th>
                            @endif
                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                <th class="text-end">Status</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>

                        @php($total = 0)
                        @php($totalQuantity = 0)

                        @foreach($purchaseOrder->lines as $line)

                            @php($total += ($line->quantity * $line->unit_cost))
                            @php($totalQuantity += $line->quantity)

                            <tr class="js-item-row" data-id="{{ $line->id }}">
                                <td>{{ $line->article_number }}</td>
                                <td>{{ $line->description }}</td>
                                <td style="width: 100px;">
                                    <input type="text" class="form-control form-control-sm text-end js-unit-cost" name="unit_cost_{{ $line->id }}" value="{{ $line->unit_cost }}" {{ $priceEditable ? '' : 'readonly' }}>
                                </td>
                                <td style="width: 100px;">
                                    <input type="text" class="form-control form-control-sm text-end js-quantity" name="quantity_{{ $line->id }}" value="{{ $line->quantity }}" {{ $quantityEditable ? '' : 'readonly' }}>
                                </td>
                                <td style="width: 100px;" class="text-end js-price">{{ number_format(($line->quantity * $line->unit_cost), 2, '.', '') }}</td>
                                <td style="width: 150px;">
                                    <input type="text" class="form-control form-control-sm text-end js-datepicker" name="shipping_date_{{ $line->id }}" value="{{ $line->getShippingDate() }}" {{ $line->is_completed ? 'readonly' : '' }}>
                                </td>
                                @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                    <td style="width: 250px;">
                                        <input type="text" class="form-control form-control-sm text-end" name="tracking_number_{{ $line->id }}" value="{{ $line->tracking_number }}" {{ $line->is_completed ? 'readonly' : '' }}>
                                    </td>
                                @endif
                                @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                    <td style="width: 150px;">
                                        <select class="form-select form-select-sm" name="status_{{ $line->id }}">
                                            <option value="">-----</option>
                                            <option value="confirm">Confirm</option>
                                            <option value="decline">Decline</option>
                                            <option value="eol">End of Life</option>
                                        </select>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-end js-total-quantity">{{ number_format($totalQuantity, 0, '.', '') }}</td>
                            <td class="text-end js-total-price">{{ number_format($total, 2, '.', '') }}</td>
                            <td></td>
                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_OPEN)
                                <td></td>
                            @endif
                            @if($portalStatus == \App\Models\PurchaseOrder::PORTAL_STATUS_UNCONFIRMED)
                                <td></td>
                            @endif
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="text-end">
                    @if($portalStatus != \App\Models\PurchaseOrder::PORTAL_STATUS_CLOSED)
                        <button class="btn btn-primary js-confirm-button" onclick="confirmOrder()">
                            <span class="spinner-border spinner-border-sm d-none"></span>
                            Confirm
                        </button>
                    @endif
                </div>
            </div>
        </div>

    </div>
@endsection

@section('script')
    <script>
        $(function() {
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

            $('.js-unit-cost, .js-quantity').on('change', function() {
                let $row = $(this).closest('.js-item-row');

                let unitCost = $row.find('.js-unit-cost').val();
                unitCost = unitCost.replace(',', '.');
                $row.find('.js-unit-cost').val(unitCost);

                let quantity = $row.find('.js-quantity').val();
                quantity = parseInt(quantity);
                $row.find('.js-quantity').val(quantity);

                let total = unitCost * quantity;
                $row.find('.js-price').text(total.toFixed(2));

                updateTotal();
            });
        });

        function updateTotal()
        {
            let total = 0;
            let totalQuantity = 0;

            $('.js-price').each(function() {
                total += parseFloat($(this).text());
            });

            $('.js-quantity').each(function() {
                totalQuantity += parseInt($(this).val());
            });

            $('.js-total-price').text(total.toFixed(2));
            $('.js-total-quantity').text(totalQuantity.toFixed(0));
        }

        function confirmOrder()
        {
            // Start loading animation
            let $button = $('.js-confirm-button');
            loadButton($button);

            // Collect post data
            let postData = {
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
            fetch('{{ route('supplierPortal.purchaseOrders.order.post', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(postData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
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
    </script>
@endsection
