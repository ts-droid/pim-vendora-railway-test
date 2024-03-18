@extends('supplierPortal.layout')

@section('content')
    <div class="container-fluid">

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Unconfirmed purchase orders</div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                <tr>
                                    <th></th>
                                    <th>Order Number</th>
                                    <th>Order Date</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($purchaseOrders['unconfirmed'] ?? null)
                                    @foreach($purchaseOrders['unconfirmed'] as $purchaseOrder)
                                        <tr>
                                            <td style="width: 1px;">
                                                <div class="row-status red"></div>
                                            </td>
                                            <td>{{ $purchaseOrder->order_number }}</td>
                                            <td>{{ $purchaseOrder->date }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-sm btn-primary">Manage</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Open purchase orders</div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                <tr>
                                    <th></th>
                                    <th>Order Number</th>
                                    <th>Order Date</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($purchaseOrders['confirmed'] ?? null)
                                    @foreach($purchaseOrders['confirmed'] as $purchaseOrder)
                                        <tr>
                                            <td style="width: 1px;">
                                                <div class="row-status red"></div>
                                            </td>
                                            <td>{{ $purchaseOrder->order_number }}</td>
                                            <td>{{ $purchaseOrder->date }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('supplierPortal.purchaseOrders.order', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-sm btn-primary">Manage</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-12">
                <div class="h5 fw-normal">Closed purchase orders</div>
                <div class="card">
                    <div class="card-body">

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
