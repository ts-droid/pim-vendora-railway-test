<!DOCTYPE html>
<html>
    <head>
        <style>
            @page {
                margin-top: 70px;
                margin-bottom: 60px;

                margin-right: 47px;
                margin-left: 47px;
            }

            body {
                margin-top: 70px;
                margin-bottom: 60px;

                margin-right: 47px;
                margin-left: 47px;
            }
        </style>
    </head>
    <body>
        <div style="font-family: Arial, Helvetica, sans-serif;font-size: 10px;">{!! $document->getFormattedDocument() !!}</div>
    </body>
</html>
