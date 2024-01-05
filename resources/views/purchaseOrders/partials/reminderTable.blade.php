@if($orderLines)
    <table>
        <tr>
            <th>SKU</th>
            <th>Description</th>
            <th style="text-align: right;">Quantity</th>
        </tr>
        @foreach($orderLines as $orderLine)
            <tr>
                <td>{{ $orderLine->article_number }}</td>
                <td>{{ $orderLine->description }}</td>
                <td style="text-align: right;">{{ $orderLine->quantity - $orderLine->quantity_received }}</td>
            </tr>
        @endforeach
    </table>
    <br>
@endif
