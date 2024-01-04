@extends('purchaseOrders.template', ['title' => 'Estimate Time of Shipping'])

@section('content')

    <div id="step-review">
        <p>Please enter the Estimated Time of Shipping for the below articles.</p>

        <div class="table">
            <table>
                <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Shipping date</th>
                    <th class="text-right">Status</th>
                </tr>
                @foreach($purchaseOrder->lines as $purchaseOrderLine)
                    @continue(!in_array($purchaseOrderLine->id, $orderLineIDs))
                    <tr class="js-item-row" data-id="{{ $purchaseOrderLine->id }}">
                        <td>{{ $purchaseOrderLine->article_number }}</td>
                        <td>{{ $purchaseOrderLine->description }}</td>
                        <td class="text-right">{{ $purchaseOrderLine->quantity }}</td>
                        <td class="text-right">
                            <input type="text" name="shipping_date_{{ $purchaseOrderLine->id }}" class="js-datepicker">
                        </td>
                        <td class="text-right">
                            <select name="status_{{ $purchaseOrderLine->id }}">
                                <option value="confirm">Confirm</option>
                                <option value="eol">End of Life</option>
                            </select>
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="button-holder" style="text-align: right;">
            <button class="button button-success" type="button" onclick="confirm()">Confirm</button>
        </div>
    </div>

    <div id="step-done" class="d-none">
        <div class="checkmark-holder">
            <div class="wrapper">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"> <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/> <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
            </div>
        </div>

        <div class="text mb-0">
            <h5>The purchase order has been updated!</h5>
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
        });

        function confirm()
        {
            // Collect post data
            let postData = {
                'items': []
            };

            $('.js-item-row').each(function() {
                let id = $(this).data('id');
                let shippingDate = $(this).find('input[name="shipping_date_' + id + '"]').val();
                let status = $(this).find('select[name="status_' + id + '"').val();

                postData['items'].push({
                    id: id,
                    shipping_date: shippingDate,
                    status: status
                });
            });

            // Validate ETA's
            for (let i = 0; i < postData['items'].length; i++) {
                if (postData['items'][i].status === 'confirm' && postData['items'][i].shipping_date === '') {
                    alert('Please enter a shipping date for all confirmed items.');
                    return;
                }
            }

            // Post the data
            fetch('{{ route('purchaseOrder.postEta', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}', {
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
                   }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
        }
    </script>

@endsection
