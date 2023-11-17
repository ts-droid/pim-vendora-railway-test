<!DOCTYPE html>
<html>

<head>
    <title>Confirm Purchase Order</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        body {
            background-color: #F3F3F3;
            padding: 0;
            margin: 0;
        }

        .d-none {
            display: none;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }

        .container {
            padding: 20px;
            width: 80%;
            max-width: 650px;
            margin: 50px auto;
            background-color: #ffffff;
            box-shadow: 0px 3px 5px #d2d2d2;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 80%;
            max-height: 30px;
        }

        .table {
            margin-bottom: 30px;
        }
        .table table {
            width: 100%;
            border-collapse: collapse;
        }
        .table table th,
        .table table td {
            text-align: left;
            border: 1px solid #cccccc;
            padding: 4px;
        }
        .table table th:last-child,
        .table table td:last-child {
            text-align: right;
        }

        .text {
            text-align: center;
            margin-bottom: 35px;
        }

        .text h5 {
            font-size: 16px;
            margin: 0;
        }

        .text p:last-child {
            margin-bottom: 0;
        }

        .button-holder {
            text-align: center;
        }

        .button {
            border: none;
            background-color: #cccccc;
            color: #000000;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 15px;
            opacity: 1;
        }
        .button:hover {
            opacity: 0.95;
        }

        .button-success {
            background-color: #17A34E;
            color: #ffffff;
        }

        .checkmark-holder {
            margin-bottom: 30px;
        }
        .checkmark-holder .wrapper{
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .checkmark-holder .checkmark__circle{
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #17A34E;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards
        }
        .checkmark-holder .checkmark{
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 10% auto;
            box-shadow: inset 0px 0px 0px #17A34E;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both
        }
        .checkmark-holder .checkmark__check{
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards
        }

        @keyframes stroke{
            100%{
                stroke-dashoffset: 0
            }
        }
        @keyframes scale{
            0%, 100%{
                transform: none
            }
            50%{
                transform: scale3d(1.1, 1.1, 1)
            }
        }
        @keyframes fill{
            100%{
                box-shadow: inset 0px 0px 0px 30px #17A34E
            }
        }
    </style>
</head>

<body>

<div class="container">
    <div class="logo">
        <img src="{{ asset('/assets/img/logo.png') }}">
    </div>

    <div id="step-review">
        <div class="table">
            <table>
                <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th>Qty</th>
                </tr>
                @if($purchaseOrder->lines)
                    @foreach($purchaseOrder->lines as $orderLine)
                        <tr>
                            <td>{{ $orderLine->article_number }}</td>
                            <td>{{ $orderLine->description }}</td>
                            <td>{{ $orderLine->quantity }} pcs</td>
                        </tr>
                    @endforeach
                @endif
            </table>
        </div>

        <div class="text">
            <p>
                Please confirm the purchase order by clicking the button below.<br>
                Else contact us at <a href="mailto:info@vendora.se">info@vendora.se</a>
            </p>
        </div>

        <div class="button-holder">
            <button class="button button-success" type="button" onclick="confirmOrder()">Confirm Purchase Order</button>
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
</div>

<script>
    function confirmOrder()
    {
        fetch('{{ route('purchaseOrder.postConfirm', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('step-review').classList.add('d-none');
                document.getElementById('step-done').classList.remove('d-none');
            }
            else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error)
        });
    }
</script>

</body>
</html>
