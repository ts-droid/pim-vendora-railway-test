@extends('monitor.layout')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-3">
                <div class="card-header">Monitors</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                            <tr>
                                <th>Server</th>
                                <th class="text-end">CPU</th>
                                <th class="text-end">RAM</th>
                                <th class="text-end">Disk</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($servers)
                                @foreach($servers as $server)
                                    <tr>
                                        <td>{{ $server['name'] }}</td>
                                        <td class="text-end">{!! \App\Http\Controllers\MonitorDashboardController::getStateBadge($server['monitorStates']['cpu_load'] ?? '') !!}</td>
                                        <td class="text-end">{!! \App\Http\Controllers\MonitorDashboardController::getStateBadge($server['monitorStates']['used_memory'] ?? '') !!}</td>
                                        <td class="text-end">{!! \App\Http\Controllers\MonitorDashboardController::getStateBadge($server['monitorStates']['disk'] ?? '') !!}</td>
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
