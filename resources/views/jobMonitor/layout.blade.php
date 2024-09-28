<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Job Monitor</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap');

        body {
            background-color: #F3F4F5;
            color: #111726;
            font-family: "Nunito", sans-serif;
        }
        .card-header {
            background-color: #FFFFFF !important;
            font-weight: 600;
            padding: 1rem;
        }
        .card-body {

        }
        th {
            background-color: #F3F4F5 !important;
        }
        th, td {
            padding: .5rem 1rem !important;
            border-bottom: 1px solid rgba(0,0,0,.125) !important;
        }
        .sub-title {
            color: #6B7280 !important;
            font-weight: 600;
        }
        .exception {
            background-color: #F3F4F5;
            padding: 0.5rem;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 3px;
            text-wrap: nowrap;
            max-width: 50vw;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        .full-exception {
            background-color: #F3F4F5;
            padding: 0.5rem;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 3px;
        }
        .shrink {
            width: 1px;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="{{ route('jobMonitor.dashboard') }}" class="btn btn-dark me-2">Dashboard</a>
            <a href="{{ route('jobMonitor.queue') }}" class="btn btn-dark me-2">Queue</a>
            <a href="{{ route('jobMonitor.failedJobs') }}" class="btn btn-dark">Failed Jobs</a>
        </div>
    </div>

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>
