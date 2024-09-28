@extends('jobMonitor.layout')

@section('content')
    <div class="row">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm mb-3">
                    <div class="card-header">Queue</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                <tr>
                                    <th>Queue</th>
                                    <th>Display Name</th>
                                    <th class="text-end">Attempts</th>
                                    <th class="text-end">Reserved at</th>
                                    <th class="text-end">Available at</th>
                                    <th class="text-end">Created at</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if($jobs)
                                    @foreach($jobs as $job)
                                        <tr>
                                            <td>{{ $job->queue }}</td>
                                            <td>{{ $job->displayName }}</td>
                                            <td class="text-end">{{ $job->attempts }}</td>
                                            <td class="text-end">{{ $job->reserved_at ? date('Y-m-d H:i:s', $job->reserved_at) : '' }}</td>
                                            <td class="text-end">{{ date('Y-m-d H:i:s', $job->available_at) }}</td>
                                            <td class="text-end">{{ date('Y-m-d H:i:s', $job->created_at) }}</td>
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
    </div>
@endsection
