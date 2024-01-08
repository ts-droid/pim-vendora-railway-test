@extends('purchaseOrders.template', ['title' => 'Confirm Purchase Order'])

@section('content')
    <div id="step-review">
        <div class="table">
            <table>
                <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th class="text-right">Unit price</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Shipping date</th>
                    <th class="text-right">Status</th>
                </tr>
                @php($total = 0)
                @php($totalQuantity = 0)

                @if($purchaseOrder->lines)
                    @foreach($purchaseOrder->lines as $orderLine)

                        @php($total += ($orderLine->quantity * $orderLine->unit_cost))
                        @php($totalQuantity += $orderLine->quantity)

                        <tr class="js-item-row" data-id="{{ $orderLine->id }}">
                            <td>{{ $orderLine->article_number }}</td>
                            <td>{{ $orderLine->description }}</td>
                            <td class="text-right">
                                <input type="text" class="text-right js-unit-price" name="unit_cost_{{ $orderLine->id }}" style="width: 80px;" value="{{ number_format($orderLine->unit_cost, 2, '.', '') }}">
                            </td>
                            <td class="text-right">{{ $orderLine->quantity }}</td>
                            <td class="text-right js-total-price">{{ number_format(($orderLine->quantity * $orderLine->unit_cost), 2, '.', '') }}</td>
                            <td class="text-right">
                                <input type="text" name="shipping_date_{{ $orderLine->id }}" class="js-datepicker" value="{{ date('Y-m-d') }}">
                            </td>
                            <td class="text-right">
                                <select name="status_{{ $orderLine->id }}">
                                    <option value="confirm">Confirm</option>
                                    <option value="eol">End of Life</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                @endif
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="text-right fw-bold">{{ number_format($totalQuantity, 2, '.', '') }}</td>
                    <td class="text-right fw-bold js-total">{{ number_format($total, 2, '.', '') }}</td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </div>

        <div class="text">
            <p>
                Please confirm the purchase order by clicking the button below.<br>
                Else contact us at <a href="mailto:info@vendora.se">info@vendora.se</a>
            </p>
        </div>

        <div class="button-holder">
            <button class="button button-success js-confirm-button" type="button" onclick="confirmOrder()">
                <div class="flex-row">
                    <span class="loader me d-none"></span>
                    <div>Confirm Purchase Order</div>
                </div>
            </button>
        </div>
    </div>

    <div id="step-done" class="d-none">
        <div class="checkmark-holder">
            <div class="wrapper">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"> <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/> <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
            </div>
        </div>

        <div class="text mb-0">
            <h5>The purchase order has been confirmed!</h5>
        </div>
    </div>

    <script>
        $(function(){
            $('.js-datepicker').each(function() {
                $(this).datepicker({
                    minDate: 0,
                    firstDay: 1,
                    dateFormat: 'yy-mm-dd'
                });
            })

            $('.js-unit-price').on('change keyup', function() {
                let $row = $(this).closest('.js-item-row');
                let quantity = $row.find('td:nth-child(4)').text();

                let unitPrice = $(this).val();
                unitPrice = unitPrice.replace(',', '.');
                $(this).val(unitPrice);

                let total = quantity * unitPrice;

                $row.find('.js-total-price').text(total.toFixed(2));

                updateTotal();
            });
        });

        function updateTotal()
        {
            let total = 0;

            $('.js-total-price').each(function() {
                total += parseFloat($(this).text());
            });

            $('.js-total').text(total.toFixed(2));
        }

        function confirmOrder()
        {
            // Start loading animation
            let $button = $('.js-confirm-button');

            // Make button disabled
            $button.prop('disabled', true);
            $button.find('.loader').removeClass('d-none');

            // Collect post data
            let postData = {
                'items': []
            };

            $('.js-item-row').each(function() {
                let id = $(this).data('id');
                let unitCost = $(this).find('input[name="unit_cost_' + id + '"]').val();
                let shippingDate = $(this).find('input[name="shipping_date_' + id + '"]').val();
                let status = $(this).find('select[name="status_' + id + '"]').val();

                postData['items'].push({
                    id: id,
                    unit_cost: unitCost,
                    shipping_date: shippingDate,
                    status: status
                });
            });

            // Validate ETA's
            for (let i = 0; i < postData['items'].length; i++) {
                if (postData['items'][i].status === 'confirm' && postData['items'][i].shipping_date === '') {
                    // Stop loading animation
                    $button.prop('disabled', false);
                    $button.find('.loader').addClass('d-none');

                    alert('Please enter a shipping date for all confirmed items.');
                    return;
                }
            }

            // Post data
            fetch('{{ route('purchaseOrder.postConfirm', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(postData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('step-review').classList.add('d-none');
                        document.getElementById('step-done').classList.remove('d-none');
                    }
                    else {
                        alert(data.message);

                        // Stop loading animation
                        $button.prop('disabled', false);
                        $button.find('.loader').addClass('d-none');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);

                    // Stop loading animation
                    $button.prop('disabled', false);
                    $button.find('.loader').addClass('d-none');
                });
        }
    </script>
@endsection
