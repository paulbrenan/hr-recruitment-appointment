@extends('layouts.app')

@section('title', 'Applications')
@section('page-title', 'Candidate applications')

@section('content')
<link rel="stylesheet" href="{{ asset('css/applications-index-polish.css') }}">
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Track candidate applications from submission through hiring</p>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" action="{{ route('applications.index') }}" class="d-flex gap-2">
            <select name="job_posting" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="" {{ request('job_posting') ? '' : 'selected' }}>All job postings</option>
                @foreach ($jobPostings as $posting)
                    <option value="{{ $posting->id }}" {{ (string) request('job_posting') === (string) $posting->id ? 'selected' : '' }}>
                        {{ $posting->title }}
                    </option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="" {{ request('status') === null || request('status') === '' ? 'selected' : '' }}>All statuses</option>
                {{--
                    "shortlisted" and "assessed" are intentionally left out of
                    this list per the current workflow simplification. They
                    still exist in the database enum (legacy records may
                    carry them), they're just not offered as filter choices.
                --}}
                @foreach (['submitted', 'screening', 'interview_scheduled', 'ranked', 'offer_sent', 'offer_accepted', 'offer_declined', 'hired', 'rejected'] as $statusOption)
                    <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>
                        {{ str_replace('_', ' ', ucfirst($statusOption)) }}
                    </option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('applications.export', request()->only(['status', 'job_posting'])) }}"
           id="export-excel-btn"
           data-no-loader
           class="btn btn-sm btn-outline-primary"
           title="{{ request('job_posting') ? 'Includes this posting\'s scoring columns — ready to fill in and re-import on Assessment & ranking' : 'Select a job posting above to include scoring columns for that posting' }}">
            <i class="bi bi-file-earmark-excel"></i> Export to Excel
        </a>
    </div>
</div>
@if (request('job_posting'))
<p class="text-muted small mb-3">
    <i class="bi bi-info-circle"></i>
    This export includes scoring columns for the selected posting — fill in scores and re-upload it on
    <strong>Assessment &amp; ranking &rarr; Import scores from Excel</strong>, no template download needed.
</p>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Job posting</th>
                    <th>Applied</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $app)
                <tr class="app-row" data-href="{{ route('applications.show', $app->id) }}">
                    <td>
                        <div class="fw-medium">{{ $app->candidate->full_name }}</div>
                        <div class="text-muted small">{{ $app->candidate->email }}</div>
                    </td>
                    <td>{{ $app->jobPosting->title }}</td>
                    <td>{{ $app->applied_at ? \Carbon\Carbon::parse($app->applied_at)->format('M d, Y') : '—' }}</td>
                    <td>
                        @php
                            $statusColors = [
                                'submitted' => 'secondary',
                                'screening' => 'info',
                                'shortlisted' => 'primary',
                                'interview_scheduled' => 'primary',
                                'assessed' => 'warning',
                                'ranked' => 'warning',
                                'offer_sent' => 'success',
                                'offer_accepted' => 'success',
                                'offer_declined' => 'danger',
                                'hired' => 'success',
                                'rejected' => 'danger',
                            ];
                        @endphp
                        <span class="badge badge-status text-bg-{{ $statusColors[$app->status] ?? 'secondary' }}">
                            {{ str_replace('_', ' ', ucfirst($app->status)) }}
                        </span>
                    </td>
                    <td class="text-end" onclick="event.stopPropagation()">
                        <a href="{{ route('applications.show', $app->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i> View
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@push('styles')
<style>
    .app-row { cursor: pointer; }
    .app-row:hover { background-color: rgba(0, 0, 0, 0.035); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/applications-index-polish.js') }}"></script>
<script>
    document.querySelectorAll('.app-row').forEach(function (row) {
        row.addEventListener('click', function () {
            window.location.href = row.dataset.href;
        });
    });

    // Export as a blob download so this page never navigates away — the
    // browser saves the file in the background and you stay right here on
    // the Applications list, whether the export succeeds or fails.
    var exportBtn = document.getElementById('export-excel-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Safety net: force the page-loader overlay closed in case
            // anything still triggers it for this click.
            var loaderOverlay = document.getElementById('deped-page-loader');
            if (loaderOverlay) {
                loaderOverlay.classList.remove('is-active');
            }

            var url = exportBtn.getAttribute('href');
            var originalHtml = exportBtn.innerHTML;
            exportBtn.classList.add('disabled');
            exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Exporting…';

            fetch(url, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        return response.text().then(function (text) {
                            throw new Error('Export failed (HTTP ' + response.status + '). ' + text.slice(0, 200));
                        });
                    }
                    var disposition = response.headers.get('Content-Disposition') || '';
                    var match = disposition.match(/filename="?([^";]+)"?/);
                    var filename = match ? match[1] : 'export.xlsx';
                    return response.blob().then(function (blob) {
                        return { blob: blob, filename: filename };
                    });
                })
                .then(function (result) {
                    var blobUrl = window.URL.createObjectURL(result.blob);
                    var tempLink = document.createElement('a');
                    tempLink.href = blobUrl;
                    tempLink.download = result.filename;
                    document.body.appendChild(tempLink);
                    tempLink.click();
                    document.body.removeChild(tempLink);
                    window.URL.revokeObjectURL(blobUrl);
                })
                .catch(function (err) {
                    alert('Could not export: ' + err.message);
                })
                .finally(function () {
                    exportBtn.classList.remove('disabled');
                    exportBtn.innerHTML = originalHtml;
                });
        });
    }
</script>
@endpush
@endsection