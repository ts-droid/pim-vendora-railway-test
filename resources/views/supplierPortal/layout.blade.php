<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Vendora Supplier Portal</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
        <link href="{{ asset('/assets/css/supplierPortal.css') }}" rel="stylesheet">
    </head>

    <body>
        @include('supplierPortal.header')

        <div class="container-fluid mt-2 mb-3">
            <div class="row">
                <div class="col-md-6">
                    @if(!empty($breadcrumbs))
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                @php($numBreadcrumbs = count($breadcrumbs))
                                @php($i = 0)
                                @foreach($breadcrumbs as $title => $url)
                                    <li class="breadcrumb-item {{ ($numBreadcrumbs - 1) == $i ? 'active' : '' }}">
                                        @if($url)
                                            <a href="{{ $url }}">{{ $title }}</a>
                                        @else
                                            {{ $title }}
                                        @endif
                                    </li>
                                    @php($i++)
                                @endforeach
                            </ol>
                        </nav>
                    @endif
                </div>
                <div class="col-md-6 text-end">
                    <b>You are logged in as:</b> {{ \App\Services\SupplierPortal\SupplierPortalAccessService::getActiveSupplier()->name }}
                </div>
            </div>
        </div>

        @yield('content')

        <div aria-live="polite" aria-atomic="true" class="position-fixed top-0 end-0 p-3" style="z-index: 11">
            <div id="toastContainer" class="toast-container">
                <!-- Toasts will go here -->
            </div>
        </div>
    </body>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="{{ asset('/assets/js/supplierPortal.js') }}"></script>

    @yield('script')
</html>
