<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $emailSubject }}</title>
</head>

<body style="margin:0;padding:0 32px;background-color:#ffffff;text-align:center;font-family:Arial, Helvetica, sans-serif;font-size:0.95rem;line-height:1.3;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">

            <table width="625" cellpadding="0" cellspacing="0" border="0" style="margin:auto;max-width:625px;width:100%;text-align:left;">

                <!-- Logo -->
                <tr>
                    <td align="center" style="padding: 20px 0;">
                        <img src="{{ \App\Services\EmailImageService::prepareImageForEmail($brandingData['logo_url']) }}" alt="Logo" style="height: 28px;margin-bottom: 1rem;" />
                    </td>
                </tr>

                <!-- Information -->
                <tr>
                    <td align="center">
                        <p style="margin:0 0 20px 0;">{{ __('request_review_text') }}</p>
                    </td>
                </tr>

                @foreach($salesOrder->lines as $line)
                    @continue($line->article_number === 'SHIP25' || $line->article_number === 'DISC25' || !$line->article)

                    <tr>
                        <td align="center">
                            <img src="{{ \App\Services\EmailImageService::prepareImageForEmail($line->article->getMainImage() ?? '') }}" style="height: 65px;width: 65px;">
                            @for($rating = 1;$rating <=5;$rating++)
                                <a href="{{ route('customer.review', ['article_id' => $line->article->id, 'lang' => app()->getLocale(), 'rating' => $rating]) }}" style="text-decoration: none !important;color: #000000 !important;margin-right: 6px;display: inline-block;">
                                    <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMiIgaGVpZ2h0PSIyMiIgZmlsbD0iY3VycmVudENvbG9yIiBjbGFzcz0iYmkgYmktc3RhciIgdmlld0JveD0iMCAwIDE2IDE2IiBzdHlsZT0ibWFyZ2luLWJvdHRvbTogMTZweDsiPgogICAgPHBhdGggZD0iTTIuODY2IDE0Ljg1Yy0uMDc4LjQ0NC4zNi43OTEuNzQ2LjU5M2w0LjM5LTIuMjU2IDQuMzg5IDIuMjU2Yy4zODYuMTk4LjgyNC0uMTQ5Ljc0Ni0uNTkybC0uODMtNC43MyAzLjUyMi0zLjM1NmMuMzMtLjMxNC4xNi0uODg4LS4yODItLjk1bC00Ljg5OC0uNjk2TDguNDY1Ljc5MmEuNTEzLjUxMyAwIDAgMC0uOTI3IDBMNS4zNTQgNS4xMmwtNC44OTguNjk2Yy0uNDQxLjA2Mi0uNjEyLjYzNi0uMjgzLjk1bDMuNTIzIDMuMzU2LS44MyA0Ljczem00LjkwNS0yLjc2Ny0zLjY4NiAxLjg5NC42OTQtMy45NTdhLjU2LjU2IDAgMCAwLS4xNjMtLjUwNUwxLjcxIDYuNzQ1bDQuMDUyLS41NzZhLjUzLjUzIDAgMCAwIC4zOTMtLjI4OEw4IDIuMjIzbDEuODQ3IDMuNjU4YS41My41MyAwIDAgMCAuMzkzLjI4OGw0LjA1Mi41NzUtMi45MDYgMi43N2EuNTYuNTYgMCAwIDAtLjE2My41MDZsLjY5NCAzLjk1Ny0zLjY4Ni0xLjg5NGEuNS41IDAgMCAwLS40NjEgMHoiLz4KPC9zdmc+Cg==" width="22" height="22" style="margin-bottom: 16px;">
                                </a>
                            @endfor
                        </td>
                    </tr>
                @endforeach

                <!-- Company information -->
                <tr>
                    <td style="padding: 20px 0;" align="center" >
                        @if($brandingData['brand_name'] != 'Vendora Nordic AB')
                            <p style="font-weight: bold;margin-bottom: 8px;">{{ __('order_confirm_distributor') }} {{ $brandingData['brand_name'] }}</p>
                        @endif
                        <p style="margin: 0;">Vendora Nordic AB</p>
                        <p style="margin: 0;">Ladugårdsvägen 1</p>
                        <p style="margin: 0;">234 35 Lomma</p>
                        <p style="margin: 0;">Sweden</p>
                        <p style="margin: 0;">{{ __('order_confirm_email') }}: info@vendora.se</p>
                        <p style="margin: 0;">{{ __('order_confirm_org_nr') }}: 556843-5456</p>
                        <p style="margin: 0;">{{ __('order_confirm_vat_nr') }}: SE556843545601</p>
                        <p style="margin: 0;">{{ __('order_confirm_hq') }}: Lomma</p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>

</html>
