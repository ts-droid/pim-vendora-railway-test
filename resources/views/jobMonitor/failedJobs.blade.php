@extends('jobMonitor.layout')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex align-items-center">
                    <div class="me-4">Failed Jobs</div>
                    <div>
                        <button class="btn btn-sm btn-dark js-retry-jobs">Retry jobs</button>
                    </div>
                    <form method="GET" class="ms-auto">
                        <select class="form-select form-select-sm" name="displayName" onchange="this.form.submit();">
                            <option value="">All Jobs</option>
                            @if($displayNames)
                                @foreach($displayNames as $displayName)
                                    <option value="{{ $displayName }}"{{ $filter['displayName'] == $displayName ? ' selected' : '' }}>{{ $displayName }}</option>
                                @endforeach
                            @endif
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                            <tr>
                                <th class="shrink">
                                    <input type="checkbox" class="form-check-input js-toggle-all">
                                </th>
                                <th>Display Name</th>
                                <th>Exception</th>
                                <th class="text-end">Queue</th>
                                <th class="text-end">Failed at</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if($failedJobs)
                                @foreach($failedJobs as $job)
                                    <tr class="align-middle">
                                        <td>
                                            <input type="checkbox" class="form-check-input job-checkbox" value="{{ $job->id }}">
                                        </td>
                                        <td>{{ $job->displayName }}</td>
                                        <td class="shrink">
                                            <div class="exception" data-bs-toggle="modal" data-bs-target="#exceptionModal" data-bs-exception="{{ $job->exception }}">{{ $job->exception }}</div>
                                        </td>
                                        <td class="text-end">{{ $job->queue }}</td>
                                        <td class="text-end">{{ $job->failed_at }}</td>
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

    <div class="modal fade" id="exceptionModal" tabindex="-1" aria-labelledby="exceptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Exception</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="full-exception"><pre class="mb-0"></pre></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        var exceptionModal = document.getElementById('exceptionModal')
        exceptionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget
            var exception = button.getAttribute('data-bs-exception')

            var exceptionHolder = exceptionModal.querySelector('.modal-body .full-exception pre')

            exceptionHolder.innerHTML = exception;
        });

        $(document).ready(function() {
            $('.js-toggle-all').on('change', function() {
                let isChecked = $(this).prop('checked');

                $('.job-checkbox').prop('checked', isChecked);
            });

            $('.js-retry-jobs').on('click', function() {
                let jobIds = [];
                $('.job-checkbox:checked').each(function() {
                    jobIds.push($(this).val());
                });

                if (jobIds.length === 0) {
                    alert('No jobs selected');
                    return;
                }

                if (!confirm('Are you sure you want to re-queue the selected jobs?')) {
                    return;
                }

                $.post('{{ route('jobMonitor.retryJobs') }}', {
                    _token: '{{ csrf_token() }}',
                    jobIDs: jobIds.join(',')
                }, function() {
                    location.reload();
                });
            });
        });
    </script>
@endsection
