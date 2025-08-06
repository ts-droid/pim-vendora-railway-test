@extends('supplierPortal.layout')

@section('content')
    <div class="container-fluid">

        <div class="row mb-5">
            <div class="col-md-12">

                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="open-tab" data-bs-toggle="tab" data-bs-target="#open" type="button" role="tab" aria-controls="open" aria-selected="true">Open</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">Completed</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="open" role="tabpanel" aria-labelledby="open-tab">
                        <div class="card">
                            <div class="card-body">
                                @include('supplierPortal.partials.purchaseOrderTable', ['orders' => ($purchaseOrders['open'] ?? null)])
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                        <div class="card">
                            <div class="card-body">
                                @include('supplierPortal.partials.purchaseOrderTable', ['orders' => ($purchaseOrders['closed'] ?? null), 'completed' => true])
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
