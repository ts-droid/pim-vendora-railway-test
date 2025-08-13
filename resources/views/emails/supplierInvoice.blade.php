An invoice have been uploaded for Purchase Order: {{ $purchaseOrder->order_number }}.

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
