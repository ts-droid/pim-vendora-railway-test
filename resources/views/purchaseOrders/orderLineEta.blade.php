@extends('purchaseOrders.template', ['title' => 'Estimated Time of Shipping'])

@section('content')

    <p>Please enter the Estimated Time of Shipping for the below articles.</p>

    <div class="table">
        <table>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>ETA</th>
                <th>Status</th>
            </tr>
            @foreach($purchaseOrder->lines as $purchaseOrderLine)
                @continue(!in_array($purchaseOrderLine->id, $orderLineIDs))
                <tr class="js-item-row" data-id="{{ $purchaseOrderLine->id }}">
                    <td>{{ $purchaseOrderLine->article_number }}</td>
                    <td>{{ $purchaseOrderLine->description }}</td>
                    <td>{{ $purchaseOrderLine->quantity }}</td>
                    <td>
                        <input type="text" name="eta_{{ $purchaseOrderLine->id }}" class="js-datepicker">
                    </td>
                    <td>
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
            let postData = [];

            $('.js-item-row').each(function() {
                let id = $(this).data('id');
                let eta = $(this).find('input').val();
                let status = $(this).find('select').val();

                postData.push({
                    id: id,
                    eta: eta,
                    status: status
                });
            });

            // Validate ETA's
            for (let i = 0; i < postData.length; i++) {
                if (postData[i].status === 'confirm' && postData[i].eta === '') {
                    alert('Please enter an ETA for all confirmed items.');
                    return;
                }
            }

            // TODO: Post the data
            console.log(postData);
        }
    </script>

@endsection
