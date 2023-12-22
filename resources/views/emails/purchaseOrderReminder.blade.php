<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>Purchase Order Reminder</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            font-weight: bold;
        }
        table th,
        table td {
            text-align: left;
            border: 1px solid #cccccc;
            padding: 0.5rem 0.5rem;
        }
    </style>

    <body>
        Dear,<br><br>

        I trust this message finds you well.<br><br>

        This serves as a reminder regarding our outstanding order. We are still awaiting updates or shipment details for the following order(s), which are expected to be fulfilled now.<br><br>

        @if($orderLines)
            <table>
                <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th>Quantity</th>
                </tr>
                @foreach($orderLines as $orderLine)
                    <tr>
                        <td>{{ $orderLine->article_number }}</td>
                        <td>{{ $orderLine->description }}</td>
                        <td>{{ $orderLine->quantity }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <br>

        <a href="{{ route('purchaseOrder.eta', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash(), 'orderLines' => implode(',', $orderLineIDs)]) }}" target="_blank">Provide delivery dates here</a>
        <br><br>

        These products' efficient and timely delivery is crucial for maintaining our inventory levels and fulfilling our customer commitments. As per our terms, we kindly request that you provide us with the following:<br><br>

        - An immediate update on the status of our order(s).<br>
        - Confirmation of expected shipping dates — please enter the Estimated Time of Shipping for each listed product by clicking on the link above. This information is vital for our planning and logistics coordination.<br><br>

        Should any issues or factors be contributing to the delay, we appreciate your transparency and would like to understand the challenges you may be facing. We aim to work collaboratively with you to find suitable solutions and prevent future delays.<br><br>

        Thank you for your immediate attention to this matter. We value our partnership and look forward to your cooperation in expediting the resolution of this issue.
    </body>
</html>
