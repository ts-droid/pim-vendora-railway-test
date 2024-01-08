@if($purchaseOrder->lines)
    @php($total = 0)
    @php($totalQuantity = 0)

    <table>
        <tr>
            <th>SKU</th>
            <th>Description</th>
            <th style="text-align: right;">Unit price</th>
            <th style="text-align: right;">Quantity</th>
            <th style="text-align: right;">Total</th>
        </tr>
        @foreach($purchaseOrder->lines as $orderLine)

            @php($total += ($orderLine->quantity * $orderLine->unit_cost))
            @php($totalQuantity += $orderLine->quantity)

            <tr>
                <td>{{ $orderLine->article_number }}</td>
                <td>{{ $orderLine->description }}</td>
                <td style="text-align: right;">{{ $orderLine->unit_cost }}</td>
                <td style="text-align: right;">{{ $orderLine->quantity }}</td>
                <td style="text-align: right;">{{ ($orderLine->unit_cost * $orderLine->quantity) }}</td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right;font-weight: bold;">{{ $totalQuantity }}</td>
            <td style="text-align: right;font-weight: bold;">{{ $total }}</td>
        </tr>
    </table>
    <br>
@endif
