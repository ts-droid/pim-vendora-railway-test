Dear {{ $recipient->name }},
<br><br>
You have been requested to sign a document. Please click the link below to review and sign the document.
<br><br>
<a href="{{ route('esign.document', ['document' => $document->id, 'secret' => $recipient->access_key]) }}" target="_blank">Sign document here</a>
<br><br>
Best regard,<br>
Vendora Nordic AB<br>
+46-(0)20-10 32 10<br>
info@vendora.se

