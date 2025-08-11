<!DOCTYPE html>
<html>
<head>
    <title>Print QR Code</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
        }
        .qrcode {
            margin-top: 50px;
        }
        .qrcode svg {
            width: 100%;
            height: auto;
            max-width: calc(100% - 100px);
        }
        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
<div class="qrcode">
    {!! $qrCode !!}
</div>

<script>
    window.onload = function() {
        window.print();

        // Wait a moment before closing (to allow print dialog to appear)
        setTimeout(function() {
            window.close();
        }, 500);
    };
</script>
</body>
</html>
