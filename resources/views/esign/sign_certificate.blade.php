@php
$badgePath = public_path('/assets/img/esign_badge.png');
$base64Badge = 'data:image/png;base64,' . base64_encode(file_get_contents($badgePath));
@endphp

<!DOCTYPE html>
<html>
    <head>
        <style>
            @page {
                margin-top: 20px;
                margin-bottom: 20px;

                margin-right: 47px;
                margin-left: 47px;
            }

            body {
                margin-top: 20px;
                margin-bottom: 20px;

                margin-right: 47px;
                margin-left: 47px;
            }
        </style>
    </head>
    <body>
        <div style="font-family: Arial, Helvetica, sans-serif;">
            <h1 style="font-size: 20px;">Signing Parties</h1>

            <div style="background-color: #F3F4F6;padding: 10px;font-size: 10px;margin-bottom: 15px;">
                <div style="font-weight: bold;margin-bottom: 5px;">Signed by {{ $document->recipient_name }}</div>
                <div style="color: #575757;">Signed at: {{ $document->signed_at }}</div>
                <div style="color: #575757;">IP: {{ $document->sign_ip }}</div>
                <div style="color: #575757;">User-agent: {{ $document->sign_user_agent }}</div>
            </div>

            <div style="font-size: 10px;margin-top: 20px;color: #575757;">
                This document has been electronically signed in accordance with the Electronic Signatures in Global and National Commerce Act (ESIGN)
                and the Uniform Electronic Transactions Act (UETA). By affixing their electronic signature, the signatory acknowledges and agrees
                that their electronic signature is legally binding and has the same legal effect as a handwritten signature.
            </div>
            <img src="{{ $base64Badge }}" style="height: 100px;opacity: 0.5;margin-top: 20px;">
        </div>
    </body>
</html>

