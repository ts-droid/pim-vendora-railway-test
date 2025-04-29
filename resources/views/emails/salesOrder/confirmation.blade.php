<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>{{ $emailSubject }}</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.95rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 300;
        }
        p {
            color: #777777;
        }

        .silent-table {
            border-collapse: collapse;
        }
        .silent-table td {
            padding: 0;
        }

        .order-table {
            border-collapse: collapse;
        }
        .order-table th,
        .order-table td {
            border: 1px solid #777777;
            padding: 0.25rem 0.5rem;
        }

        .text-start {
            text-align: left !important;
        }
        .text-end {
            text-align: right !important;
        }
    </style>
</head>

<body>

<img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('/assets/img/logos/logo_vendora.png'))) }}" style="height: 28px;margin-bottom: 1rem;" />
<h1>Thank you for your purchase!</h1>
<p>Hi Anton! We are preparing your order for delivery. We will notify you when it has been shipped.</p>

<br>

<table class="silent-table">
    <tr>
        <td style="padding-right: 4rem;">
            <h2>Billing Address</h2>
            <p>
                Anton Kihlström<br>
                Kastellvägen 21<br>
                82450, Hudiksvall<br>
                Sweden
            </p>
        </td>
        <td>
            <h2>Shipping Address</h2>
            <p>
                Evelina Collén<br>
                Falkvägen 25B<br>
                83156, Sollefteå<br>
                Sweden
            </p>
        </td>
    </tr>
</table>

<br>

<table class="order-table">
    <tr>
        <th class="text-start">SKU</th>
        <th class="text-start">Description</th>
        <th class="text-end">Unit price</th>
        <th class="text-end">Quantity</th>
        <th class="text-end">Total</th>
    </tr>
    <tr>
        <td class="text-start">PL2A-11-24</td>
        <td class="text-start">Paperlike 21 skärmskydd för iPad Pro 11 2024 (2-pack)</td>
        <td class="text-end">202.59</td>
        <td class="text-end">2 pcs</td>
        <td class="text-end">405.18</td>
    </tr>
    <tr>
        <td class="text-start">P052-51-V</td>
        <td class="text-start">Pipetto iPad 109-tum (10e gen) Origami No1 Original - Marinblå</td>
        <td class="text-end">292.79</td>
        <td class="text-end">4 pcs</td>
        <td class="text-end">1 171.16</td>
    </tr>
    <tr>
        <th colspan="3"></th>
        <th class="text-end">6 pcs</th>
        <th class="text-end">1 576.34</th>
    </tr>
</table>

<br>

<p>
    This is an order confirmation only. Your order will be processed shortly, and you will receive a separate email
    once your items have been shipped. If you have any questions, please contact our customer support.
    Thank you for your purchase!
</p>

</body>
</html>
