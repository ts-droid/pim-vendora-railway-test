@extends('jobMonitor.layout')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-3">
                <div class="card-header">Overview</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="sub-title mb-1">Jobs in Queue</div>
                            <div class="h4 mb-0">{{ number_format($jobsInQueue, 0, '', ',') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="sub-title mb-1">Failed Jobs</div>
                            <div class="h4 mb-0">{{ number_format($failedJobs, 0, '', ',') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-3">
                <div class="card-header">Current Workload</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                            <tr>
                                <th>Queue</th>
                                <th class="text-end">Jobs</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($workload)
                                @foreach($workload as $row)
                                    <tr>
                                        <td>{{ $row->queue }}</td>
                                        <td class="text-end">{{ $row->num_jobs }}</td>
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
@endsection
