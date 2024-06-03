<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $document->name }}</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="{{ asset('/assets/css/esign.css') }}" rel="stylesheet">
    </head>
    <body>
        <div class="container py-5">
            <div class="row">
                <div class="col-md-10 offset-md-1">
                    <div class="document-info mb-4">
                        <h1 class="h3">{{ $document->name }}</h1>
                        <div><b>Sent at:</b> {{ $document->sent_at }}</div>
                        <div><b>Recipient:</b> {{ $document->recipient_name }} ({{ $document->recipient_email }})</div>
                    </div>
                    <div class="document-preview mb-4">
                        <div id="pdf-container"></div>
                    </div>
                    @if($document->signed_at)
                        <div class="document-info">
                            <div>This document was signed at <b>{{ $document->signed_at }}</b></div>
                            <br>
                            <div class="mb-4">
                                <b>Signed by:</b><br>
                                {{ $document->recipient_name }} ({{ $document->recipient_email }})<br>
                                <small>{{ $document->sign_ip }}</small><br>
                                <small>{{ $document->sign_user_agent }}</small>
                            </div>
                            <a href="{{ route('esign.document.download', ['document' => $document->id, 'secret' => $document->getAccessHash()]) }}" class="document-download-button"><i class="bi bi-download me-1"></i> Download Signed Document</a>
                        </div>
                    @else
                        <div class="text-end">
                            <a class="document-sign-button" href="{{ route('esign.document.sign', ['document' => $document->id, 'secret' => $document->getAccessHash()]) }}"><i class="bi bi-check-lg"></i> Sign Document</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </body>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.10.377/pdf.worker.min.js"></script>
    <script>
        // Base64 encoded PDF string
        const base64PDF = '{{ $document->base64PDF() }}';

        // Convert base64 to Uint8Array
        function base64ToUint8Array(base64) {
            const binaryString = atob(base64);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            return bytes;
        }

        // Get the PDF container
        const pdfContainer = document.getElementById('pdf-container');

        // Convert base64 string to Uint8Array
        const pdfData = base64ToUint8Array(base64PDF);

        const isMobile = window.matchMedia("only screen and (max-width: 760px)").matches;

        // Load the PDF
        const loadingTask = pdfjsLib.getDocument({ data: pdfData });
        loadingTask.promise.then(function(pdf) {
            const renderPage = function(pageNumber) {
                pdf.getPage(pageNumber).then(function(page) {
                    const scale = isMobile ? 0.5 : 1.5;
                    const viewport = page.getViewport({ scale: scale });

                    // Create a canvas element for rendering
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');

                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    canvas.style.marginBottom = '20px';

                    // Append the canvas to the container
                    pdfContainer.appendChild(canvas);

                    // Render the page into the canvas context
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };

                    page.render(renderContext).promise.then(function() {
                        if (pageNumber < pdf.numPages) {
                            renderPage(pageNumber + 1);
                        }
                    });
                });
            }

            renderPage(1);
        });
    </script>
</html>
