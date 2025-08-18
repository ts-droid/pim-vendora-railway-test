An invoice have been uploaded for Purchase Order: {{ $purchaseOrder->id }} ({{ $purchaseOrder->order_number }}).

<br><br>

@if($purchaseOrder->is_direct)
    <h2>Direct Delivery!</h2><br>
    The purchase order is a direct delivery. You must handle the delivery manually using the below link:<br>
    <a href="https://adm.vendora.se/purchase-orders/{{ $purchaseOrder->id }}" target="_blank">https://adm.vendora.se/purchase-orders/{{ $purchaseOrder->id }}</a>
@endif

<br><br>

The invoice is associated with the following order lines:<br>
<table>
    <tr>
        <th>SKU</th>
        <th>Description</th>
        <th>Quantity</th>
    </tr>
    @foreach($purchaseOrder->lines as $line)
        @continue(!in_array($line->id, $purchaseOrderLineIDs))
        <tr>
            <td>{{ $line->article_number }}</td>
            <td>{{ $line->description }}</td>
            <td>{{ $line->quantity }}</td>
        </tr>
    @endforeach
</table>

<br><br>

Download the invoice here: <a href="{{ $fileUrl }}">{{ $fileUrl }}</a>
