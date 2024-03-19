@extends('supplierPortal.layout')

@section('content')
    <div class="container-fluid">

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Unconfirmed purchase orders</div>
                <div class="card">
                    <div class="card-body">
                        @include('supplierPortal.partials.purchaseOrderTable', ['orders' => ($purchaseOrders['unconfirmed'] ?? null)])
                    </div>
                </div>

            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Open purchase orders</div>
                <div class="card">
                    <div class="card-body">
                        @include('supplierPortal.partials.purchaseOrderTable', ['orders' => ($purchaseOrders['confirmed'] ?? null)])
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Closed purchase orders</div>
                <div class="card">
                    <div class="card-body">
                        @include('supplierPortal.partials.purchaseOrderTable', ['orders' => ($purchaseOrders['closed'] ?? null)])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
