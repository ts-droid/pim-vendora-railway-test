@if($purchaseOrder->lines)
    <table>
        <tr>
            <th>SKU</th>
            <th>Description</th>
            <th style="text-align: right;">Quantity</th>
            <th style="text-align: right;">Unit price</th>
            <th style="text-align: right;">Total</th>
        </tr>
        @foreach($purchaseOrder->lines as $orderLine)
            <tr>
                <td>{{ $orderLine->article_number }}</td>
                <td>{{ $orderLine->description }}</td>
                <td style="text-align: right;">{{ $orderLine->quantity }}</td>
                <td style="text-align: right;">{{ $orderLine->unit_cost }}</td>
                <td style="text-align: right;">{{ ($orderLine->unit_cost * $orderLine->quantity) }}</td>
            </tr>
        @endforeach
    </table>
    <br>
@endif
