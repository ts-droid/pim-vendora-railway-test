<div style="font-family: Arial, Helvetica, sans-serif;">
    <h1>Signing party</h1>
    <div style="background-color: #F3F4F6;padding: 1rem;">
        <b>Signed by {{ $document->recipient_name }}</b>
        <div style="color: #575757;">Signed at: {{ $document->signed_at }}</div>
        <div style="color: #575757;">IP: {{ $document->sign_ip }}</div>
        <div style="color: #575757;">User-agent: {{ $document->sign_user_agent }}</div>
    </div>

</div>
