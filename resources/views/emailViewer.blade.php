<!DOCTYPE html>
<html>
<head>
    <title>{{ $email->subject }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }
        .container {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table td {
            border: 1px solid #CCCCCC;
            padding: 0.5rem;
        }
        .text-start {
            text-align: left !important;
        }
        .text-end {
            text-align: right !important;
        }

        .preview-iframe {
            border: 1px solid #CCCCCC;
        }
    </style>
</head>
<body>
<div class="container">
    <table class="table">
        <tr>
            <td class="text-start"><b>Time:</b></td>
            <td class="text-end">{{ date('Y-m-d H:i:s', strtotime($email->created_at)) }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>Subject:</b></td>
            <td class="text-end">{{ $email->subject }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>Recipient:</b></td>
            <td class="text-end">{{ $email->to }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>CC:</b></td>
            <td class="text-end">{{ $email->cc }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>BCC:</b></td>
            <td class="text-end">{{ $email->bcc }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>Sender name:</b></td>
            <td class="text-end">{{ $email->sender_name }}</td>
        </tr>
        <tr>
            <td class="text-start"><b>Sender email:</b></td>
            <td class="text-end">{{ $email->sender_email }}</td>
        </tr>
    </table>

    <br>

    <iframe id="iframe" style="width: 100%; height: 750px;" class="preview-iframe"></iframe>
</div>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        const rawHTML = @json($email->body);
        const iframe = document.getElementById('iframe');
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(rawHTML);
        doc.close();
    });
</script>
</body>
</html>
